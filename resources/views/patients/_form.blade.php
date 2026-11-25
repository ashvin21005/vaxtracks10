<h2 class="form-section-title">Child Information</h2>
<div class="form-grid">
    <div class="field">
        <label for="last_name">Last Name <span class="req">*</span></label>
        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $child->last_name) }}" class="@error('last_name') is-invalid @enderror">
        @error('last_name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="first_name">First Name <span class="req">*</span></label>
        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $child->first_name) }}" class="@error('first_name') is-invalid @enderror">
        @error('first_name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="middle_name">Middle Name</label>
        <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $child->middle_name) }}" class="@error('middle_name') is-invalid @enderror">
        @error('middle_name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="sex">Sex <span class="req">*</span></label>
        <select id="sex" name="sex" class="@error('sex') is-invalid @enderror">
            <option value="">Select sex</option>
            @foreach (['Male', 'Female'] as $option)
                <option value="{{ $option }}" @selected(old('sex', $child->sex) === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error('sex')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="date_of_birth">Date of Birth <span class="req">*</span></label>
        <input type="date" id="date_of_birth" name="date_of_birth" max="{{ date('Y-m-d') }}"
            value="{{ old('date_of_birth', $child->date_of_birth?->format('Y-m-d')) }}" class="@error('date_of_birth') is-invalid @enderror">
        @error('date_of_birth')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="place_of_birth_type">Place of Birth</label>
        @php($birthPlaceOptions = ['Hospital', 'Birthing Facility', 'Other'])
        @php($birthPlaceType = old('place_of_birth_type', $child->place_of_birth_type ?: (in_array($child->place_of_birth, $birthPlaceOptions, true) ? $child->place_of_birth : ($child->place_of_birth ? 'Other' : ''))))
        <select id="place_of_birth_type" name="place_of_birth_type" data-birth-place-type>
            <option value="">Select place</option>
            @foreach ($birthPlaceOptions as $option)
                <option value="{{ $option }}" @selected($birthPlaceType === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="birth_facility">Birth Facility</label>
        <select id="birth_facility_select" name="birth_facility" data-birth-facility-select data-current="{{ old('birth_facility', $child->birth_facility) }}" @if (! in_array($birthPlaceType, ['Hospital', 'Birthing Facility'], true)) hidden @endif>
            <option value="">Select facility</option>
        </select>
        <input type="text" id="birth_facility_other" name="birth_facility" value="{{ old('birth_facility', $child->birth_facility) }}" maxlength="150" data-birth-facility-other @if ($birthPlaceType !== 'Other') hidden @endif>
        @error('birth_facility')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="birth_weight">Birth Weight (kg)</label>
        <input type="number" step="0.01" min="0.5" max="9.99" id="birth_weight" name="birth_weight" value="{{ old('birth_weight', $child->birth_weight) }}" class="@error('birth_weight') is-invalid @enderror">
        @error('birth_weight')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field field-wide">
        <label for="address">Address</label>
        <input type="text" id="address" name="address_preview" value="{{ old('address', $child->address) }}" readonly data-child-address-preview>
    </div>

    <div class="field">
        <label for="purok">Purok</label>
        <input type="text" id="purok" name="purok" value="{{ old('purok', $child->purok) }}" maxlength="100">
        @error('purok')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="street">Street</label>
        <input type="text" id="street" name="street" value="{{ old('street', $child->street ?: ($child->address && ! $child->purok ? $child->address : '')) }}" maxlength="150">
        @error('street')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="barangay">Barangay <span class="req">*</span></label>
        <select id="barangay" name="barangay" class="@error('barangay') is-invalid @enderror" required>
            <option value="">Select a barangay</option>
            @if ($child->exists && $child->barangay && ! $barangays->contains('official_name', $child->barangay))
                <option value="{{ $child->barangay }}" @selected(old('barangay', $child->barangay) === $child->barangay)>{{ $child->barangay }} (existing record)</option>
            @endif
            @foreach ($barangays as $option)
                <option value="{{ $option->official_name }}" @selected(old('barangay', $child->barangay) === $option->official_name)>{{ $option->official_name }}@if ($option->name) - {{ $option->name }}@endif</option>
            @endforeach
        </select>
        @error('barangay')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    @if (auth()->user()->isCho())
        <div class="field">
            <label for="health_station_id">Health Station</label>
            <select id="health_station_id" name="health_station_id">
                <option value="">Not assigned</option>
                @foreach ($stations as $station)
                    <option value="{{ $station->id }}" @selected((string) old('health_station_id', $child->health_station_id) === (string) $station->id)>{{ $station->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($child->exists)
        <div class="field">
            <label for="status">Patient Status <span class="req">*</span></label>
            <select id="status" name="status" required>
                @foreach (['ACTIVE', 'INACTIVE'] as $option)
                    <option value="{{ $option }}" @selected(old('status', $child->status) === $option)>{{ ucfirst(strtolower($option)) }}</option>
                @endforeach
            </select>
            @error('status')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    @endif

</div>

<h2 class="form-section-title">Guardian Information</h2>
<div class="form-grid">
    <div class="field">
        <label for="guardian_last_name">Last Name <span class="req">*</span></label>
        <input type="text" id="guardian_last_name" name="guardian_last_name" value="{{ old('guardian_last_name', $guardian->last_name) }}" class="@error('guardian_last_name') is-invalid @enderror">
        @error('guardian_last_name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="guardian_first_name">First Name <span class="req">*</span></label>
        <input type="text" id="guardian_first_name" name="guardian_first_name" value="{{ old('guardian_first_name', $guardian->first_name) }}" class="@error('guardian_first_name') is-invalid @enderror">
        @error('guardian_first_name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="guardian_middle_name">M.I.</label>
        <input type="text" id="guardian_middle_name" name="guardian_middle_name" value="{{ old('guardian_middle_name', $guardian->middle_name) }}" maxlength="10">
        @error('guardian_middle_name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="guardian_relationship">Relationship to Child <span class="req">*</span></label>
        <select id="guardian_relationship" name="guardian_relationship" class="@error('guardian_relationship') is-invalid @enderror">
            <option value="">Select relationship</option>
            @foreach (['Mother', 'Father', 'Grandparent', 'Legal Guardian', 'Other'] as $option)
                <option value="{{ $option }}" @selected(old('guardian_relationship', $guardian->relationship) === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error('guardian_relationship')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="guardian_contact_number">Contact Number <span class="req">*</span></label>
        <input type="tel" id="guardian_contact_number" name="guardian_contact_number" placeholder="09XXXXXXXXX"
            value="{{ old('guardian_contact_number', $guardian->contact_number) }}" class="@error('guardian_contact_number') is-invalid @enderror">
        @error('guardian_contact_number')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label for="guardian_alternative_contact_number">Alternative Contact Number</label>
        <input type="tel" id="guardian_alternative_contact_number" name="guardian_alternative_contact_number" placeholder="09XXXXXXXXX" value="{{ old('guardian_alternative_contact_number', $guardian->alternative_contact_number) }}">
        @error('guardian_alternative_contact_number')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="field field-wide">
        <label for="guardian_address">Address</label>
        <input type="text" id="guardian_address" name="guardian_address" value="{{ old('guardian_address', $guardian->address) }}" data-guardian-address>
        @error('guardian_address')<p class="field-error">{{ $message }}</p>@enderror
        <label class="checkbox-label guardian-same-address"><input type="checkbox" id="guardian_address_same_as_child" name="guardian_address_same_as_child" value="1" data-guardian-same-address @checked(old('guardian_address_same_as_child', $guardian->address && $guardian->address === implode(', ', array_filter([$child->address, $child->barangay])) ? '1' : null))>Same as Child's Address</label>
    </div>
</div>

<script>
    (() => {
        const form = document.currentScript.closest('form');
        if (!form) return;
        const placeType = form.querySelector('[data-birth-place-type]');
        const otherPlace = form.querySelector('[data-birth-place-other]');
        const facilitySelect = form.querySelector('[data-birth-facility-select]');
        const facilityOther = form.querySelector('[data-birth-facility-other]');
        const sameAddress = form.querySelector('[data-guardian-same-address]');
        const guardianAddress = form.querySelector('[data-guardian-address]');
        const addressPreview = form.querySelector('[data-child-address-preview]');
        const facilityLists = {
            'Hospital': [
                'Eastern Visayas Medical Center (EVMC) - Magsaysay Blvd, Downtown',
                'Tacloban City Hospital - Marasbaras',
                'ACE Medical Center-Tacloban - Tabuan National Hwy, Brgy. 78 (Marasbaras)',
                'Divine Word Hospital - M.H. Del Pilar St, Downtown',
                'Mother of Mercy Hospital - Ninoy Aquino Ave',
                'Remedios Trinidad Romualdez (RTR) Hospital - Calanipawan Rd',
                'United Shalom Hospital - Real St',
                'Tacloban Maternity Hospital - Justice Romualdez St, Downtown'
            ],
            'Birthing Facility': [
                'Sagkahan District Health and Birthing Facility - 647 Real St, Brgy. 61 (Sagkahan)',
                'Abucay District Health Center & Birthing Facility - Tacloban-Babatngon Rd, Abucay',
                'V&G District Health Center - Birthing Facility - Brgy. 95-A, V&G Subdivision',
                'Mercy Grace Birthing Clinic - Jones St, Downtown',
                'Montana-Merin Birthing Clinic - Phase 4, Los Angeles St, V&G Subdivision',
                'Holy Child Maternity and Ultrasound Clinic - P&M Subdivision, Brgy. 91 (Abucay)',
                'Divine Rays Family Health Care Center - Malaki Subd, Abucay'
            ]
        };
        const childAddress = () => {
            const parts = [form.elements.address?.value, form.elements.purok?.value, form.elements.street?.value, form.elements.barangay?.selectedOptions[0]?.textContent?.trim()].filter(Boolean);
            const base = [form.elements.purok?.value, form.elements.street?.value, form.elements.barangay?.selectedOptions[0]?.textContent?.trim()].filter(Boolean).join(', ');
            if (addressPreview) addressPreview.value = base ? `${base}, Tacloban City` : '';
            return base;
        };
        const updatePlace = () => {
            const place = placeType?.value || '';
            if (otherPlace) otherPlace.hidden = place !== 'Other';
            const isListed = place === 'Hospital' || place === 'Birthing Facility';
            if (facilitySelect) {
                facilitySelect.hidden = !isListed;
                facilitySelect.disabled = !isListed;
                facilitySelect.innerHTML = '<option value="">Select facility</option>';
                if (isListed) facilityLists[place].forEach((facility) => {
                    const option = document.createElement('option');
                    option.value = facility;
                    option.textContent = facility;
                    option.selected = facility === facilitySelect.dataset.current;
                    facilitySelect.append(option);
                });
            }
            if (facilityOther) {
                facilityOther.hidden = place !== 'Other';
                facilityOther.disabled = place !== 'Other';
            }
        };
        placeType?.addEventListener('change', updatePlace);
        updatePlace();
        const syncGuardianAddress = () => {
            const base = childAddress();
            if (!sameAddress?.checked) return;
            guardianAddress.value = base ? `${base}, Tacloban City` : '';
        };
        sameAddress?.addEventListener('change', syncGuardianAddress);
        form.elements.purok?.addEventListener('input', () => { childAddress(); syncGuardianAddress(); });
        form.elements.street?.addEventListener('input', () => { childAddress(); syncGuardianAddress(); });
        form.elements.barangay?.addEventListener('change', () => { childAddress(); syncGuardianAddress(); });
        childAddress();
        syncGuardianAddress();
    })();
</script>