<section class="panel historical-immunizations" data-historical-immunizations data-mode="{{ $mode }}">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Previously administered vaccines</h2>
            <p class="page-subtitle">Record administered or not administered doses. Historical entries never deduct current station stock.</p>
        </div>
        @if ($mode === 'collection')
            <button type="button" class="btn btn-secondary" data-add-historical-dose>Add dose</button>
        @endif
    </div>
    <div class="historical-dose-list" data-historical-dose-list></div>
    <template data-historical-dose-template>
        <fieldset class="historical-dose" data-historical-dose>
            <legend>Historical dose</legend>
            <label>Vaccination history status
                <select data-field="history_status" data-history-status required>
                    <option value="ADMINISTERED">Administered</option>
                    <option value="NOT_ADMINISTERED">Not administered</option>
                </select>
            </label>
            <div class="record-form-grid">
                <label>Vaccine
                    <select data-field="vaccine_id" data-historical-vaccine required>
                        <option value="">Select vaccine</option>
                        @foreach ($historicalVaccines as $vaccine)
                            @php($vaccineLabels = [
                                'BCG' => 'Bacillus Calmette-Guerin (BCG)',
                                'HEPB_BIRTH' => 'Hepatitis B Birth Dose (HepB)',
                                'PENTAVALENT' => 'Pentavalent Vaccine (Pentavalent)',
                                'OPV' => 'Oral Polio Vaccine (OPV)',
                                'IPV' => 'Inactivated Polio Vaccine (IPV)',
                                'PCV' => 'Pneumococcal Conjugate Vaccine (PCV)',
                                'MMR' => 'Measles, Mumps and Rubella Vaccine (MMR)' 
                            ])
                            <option value="{{ $vaccine->id }}" data-dose-count="{{ count(config('immunization.epi.'.$vaccine->epi_code.'.doses', [])) }}" data-epi-code="{{ $vaccine->epi_code }}">{{ $vaccineLabels[$vaccine->epi_code] ?? $vaccine->name.' ('.$vaccine->epi_code.')' }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Dose number
                    <select data-field="dose_number" required>
                        <option value="">Select dose</option>
                        @foreach (range(1, 3) as $doseNumber)
                            <option value="{{ $doseNumber }}">Dose {{ $doseNumber }}</option>
                        @endforeach
                    </select>
                </label>
                <label data-history-administered>Date precision
                    <select data-field="date_precision" data-date-precision required>
                        <option value="DAY">Exact date known</option>
                        <option value="MONTH">Only month and year known</option>
                    </select>
                </label>
                <label data-history-administered data-date-day>Administration date
                    <input type="date" data-field="administered_at" max="{{ today()->toDateString() }}">
                </label>
                <label data-history-administered data-date-month hidden>Administration month
                    <select data-field="administered_month">
                        <option value="">Select month</option>
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}">{{ \Illuminate\Support\Carbon::create()->month($month)->format('F') }}</option>
                        @endforeach
                    </select>
                </label>
                <label data-history-administered data-date-year hidden>Administration year
                    <input type="number" data-field="administered_year" min="1900" max="{{ today()->year }}" inputmode="numeric">
                </label>
            </div>
            <label class="historical-dose-confirm" data-history-administered>
                <input type="checkbox" data-field="verified" value="1" data-history-verified>
                I have verified this vaccination using the available vaccination record.
            </label>
            <p class="page-subtitle" data-history-status-hint></p>
            <button type="button" class="btn btn-sm btn-secondary" data-remove-historical-dose>Remove dose</button>
        </fieldset>
    </template>
</section>
<script src="{{ asset('js/historical-immunizations.js') }}" defer></script>
