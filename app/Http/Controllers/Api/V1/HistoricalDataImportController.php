<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\HistoricalDataImportBatch;
use App\Models\HistoricalDataImportRow;
use App\Models\HistoricalIndicator;
use App\Models\Vaccine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class HistoricalDataImportController extends Controller
{
    private const METRIC_TYPES = ['IMMUNIZATION_DOSES', 'VACCINE_CONSUMPTION', 'DISEASE_INCIDENTS'];

    private const REQUIRED_COLUMNS = ['month', 'barangay', 'metric_type', 'vaccine', 'disease', 'value'];

    private const MAX_ROWS = 5000;

    public function index()
    {
        return HistoricalDataImportBatch::with('importer:id,name')
            ->withCount(['rows', 'indicators'])
            ->latest()
            ->paginate(25);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source_name' => ['required', 'string', 'max:200'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'],
        ]);
        $extension = strtolower($data['file']->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt'], true)) {
            return response()->json(['message' => 'Upload a CSV file.'], 422);
        }

        $stream = fopen($data['file']->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read the uploaded historical-data file.');
        }

        try {
            $headers = fgetcsv($stream, 0, ',', '"', '\\');
            if ($headers === false) {
                return response()->json(['message' => 'The uploaded CSV file is empty.'], 422);
            }

            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }
            if (count($headers) !== count(array_unique($headers))
                || array_diff(self::REQUIRED_COLUMNS, $headers)) {
                return response()->json([
                    'message' => 'The CSV header must contain each required column exactly once.',
                    'required_columns' => self::REQUIRED_COLUMNS,
                ], 422);
            }

            $dataRows = 0;
            while (($columns = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
                if ($columns !== [null] && (count($columns) !== 1 || trim((string) $columns[0]) !== '')) {
                    $dataRows++;
                    if ($dataRows > self::MAX_ROWS) {
                        return response()->json(['message' => 'The CSV may contain no more than 5000 data rows.'], 422);
                    }
                }
            }
            rewind($stream);
            fgetcsv($stream, 0, ',', '"', '\\');

            $barangayIds = Barangay::query()->pluck('id', 'official_name')->all();
            $vaccineIds = Vaccine::query()->pluck('id', 'name')->all();
            $batch = HistoricalDataImportBatch::create([
                'source_name' => $data['source_name'],
                'source_reference' => $data['source_reference'] ?? null,
                'imported_by' => $request->user()->id,
                'status' => 'PROCESSING',
            ]);
            $rowNumber = 1;
            $accepted = 0;
            $rejected = 0;
            $rejectedRows = [];

            while (($columns = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
                $rowNumber++;
                if ($columns === [null] || (count($columns) === 1 && trim((string) $columns[0]) === '')) {
                    continue;
                }

                if (count($columns) !== count($headers)) {
                    $raw = ['_columns' => $columns];
                    $errors = ['row' => ['The number of values does not match the CSV header.']];
                    $this->saveRejectedRow($batch, $rowNumber, $raw, $errors);
                    $rejectedRows[] = ['row_number' => $rowNumber, 'errors' => $errors];
                    $rejected++;

                    continue;
                }

                $raw = array_combine($headers, $columns);
                $normalized = $this->normalizeRow($raw);
                $validator = Validator::make($normalized, [
                    'month' => ['required', 'date_format:Y-m'],
                    'barangay' => ['required', 'string', Rule::in(array_keys($barangayIds))],
                    'metric_type' => ['required', Rule::in(self::METRIC_TYPES)],
                    'vaccine' => [
                        'nullable',
                        'required_if:metric_type,IMMUNIZATION_DOSES,VACCINE_CONSUMPTION',
                        'prohibited_unless:metric_type,IMMUNIZATION_DOSES,VACCINE_CONSUMPTION',
                        'string',
                        'max:150',
                        Rule::in(array_keys($vaccineIds)),
                    ],
                    'disease' => [
                        'nullable',
                        'required_if:metric_type,DISEASE_INCIDENTS',
                        'prohibited_unless:metric_type,DISEASE_INCIDENTS',
                        'string',
                        'max:150',
                    ],
                    'value' => ['required', 'integer', 'min:0', 'max:4294967295'],
                ]);

                if ($validator->fails()) {
                    $errors = $validator->errors()->toArray();
                    $this->saveRejectedRow($batch, $rowNumber, $raw, $errors);
                    $rejectedRows[] = ['row_number' => $rowNumber, 'errors' => $errors];
                    $rejected++;

                    continue;
                }

                DB::transaction(function () use ($batch, $normalized, $raw, $rowNumber, &$accepted, $barangayIds, $vaccineIds) {
                    $importRow = HistoricalDataImportRow::create([
                        'batch_id' => $batch->id,
                        'row_number' => $rowNumber,
                        'raw_data' => $raw,
                        'status' => 'ACCEPTED',
                    ]);
                    HistoricalIndicator::create([
                        'batch_id' => $batch->id,
                        'import_row_id' => $importRow->id,
                        'period_start' => $normalized['month'].'-01',
                        'barangay_id' => $barangayIds[$normalized['barangay']],
                        'metric_type' => $normalized['metric_type'],
                        'vaccine_id' => $normalized['vaccine'] ? $vaccineIds[$normalized['vaccine']] : null,
                        'disease_name' => $normalized['disease'],
                        'value' => $normalized['value'],
                        'source_name' => $batch->source_name,
                        'source_reference' => $batch->source_reference,
                    ]);
                    $accepted++;
                });
            }
        } finally {
            fclose($stream);
        }

        $total = $accepted + $rejected;
        $status = $accepted === 0 ? 'FAILED' : ($rejected > 0 ? 'PARTIAL' : 'COMPLETED');
        $batch->update([
            'total_rows' => $total,
            'accepted_rows' => $accepted,
            'rejected_rows' => $rejected,
            'status' => $status,
        ]);

        return response()->json([
            'data' => $batch->fresh(),
            'rejected_rows' => $rejectedRows,
        ], 201);
    }

    public function show(HistoricalDataImportBatch $batch)
    {
        $batch->load('importer:id,name')->loadCount(['rows', 'indicators']);

        return response()->json([
            'data' => $batch,
            'rows' => $batch->rows()->orderBy('row_number')->paginate(100),
        ]);
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach (self::REQUIRED_COLUMNS as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            $normalized[$column] = $value === '' ? null : $value;
        }
        if ($normalized['metric_type'] !== null) {
            $normalized['metric_type'] = strtoupper($normalized['metric_type']);
        }

        return $normalized;
    }

    private function saveRejectedRow(HistoricalDataImportBatch $batch, int $rowNumber, array $raw, array $errors): void
    {
        HistoricalDataImportRow::create([
            'batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'raw_data' => $raw,
            'validation_errors' => $errors,
            'status' => 'REJECTED',
        ]);
    }
}
