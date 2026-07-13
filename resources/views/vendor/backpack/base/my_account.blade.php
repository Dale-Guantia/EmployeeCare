@extends(backpack_view('blank'))

@section('after_styles')
    <style media="screen">
        .backpack-profile-form .required::after {
            content: ' *';
            color: red;
        }
    </style>
@endsection

@php
  $breadcrumbs = [
      trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
      trans('backpack::base.my_account') => false,
  ];
@endphp

@section('header')
    <section class="content-header">
        <div class="container-fluid mb-3">
            <h1>{{ trans('backpack::base.my_account') }}</h1>
        </div>
    </section>
@endsection

@section('content')
    <div class="row">

        @if (session('success'))
        <div class="col-lg-8">
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        </div>
        @endif

        @if ($errors->count())
        <div class="col-lg-8">
            <div class="alert alert-danger">
                <ul class="mb-1">
                    @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- UPDATE INFO FORM --}}
        <div class="col-lg-10">
            <form class="form" action="{{ route('backpack.account.info.store') }}" method="post" enctype="multipart/form-data">

                {!! csrf_field() !!}

                <div class="card padding-10">

                    <div class="card-header">
                        {{ trans('backpack::base.update_account_info') }}
                    </div>

                    <div class="card-body backpack-profile-form bold-labels">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                @php
                                    $label = trans('backpack::base.name');
                                    $field = 'name';
                                @endphp
                                <label class="required">{{ $label }}</label>
                                <input required class="form-control" type="text" name="{{ $field }}" value="{{ old($field) ? old($field) : $user->$field }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="required">Username</label>
                                <input
                                    required
                                    class="form-control"
                                    type="text"
                                    name="username"
                                    value="{{ old('username', $user->username ?? '') }}"
                                >
                            </div>
                            <div class="col-md-6 form-group">
                                @php
                                    $label = config('backpack.base.authentication_column_name');
                                    $field = backpack_authentication_column();
                                @endphp
                                <label>{{ $label }}</label>
                                <input required class="form-control" type="{{ backpack_authentication_column()==backpack_email_column()?'email':'text' }}" name="{{ $field }}" value="{{ old($field) ? old($field) : $user->$field }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">User Role</label>
                                <p class="text-muted bg-light p-2 rounded border">
                                    {{ optional(backpack_user()->roles->first())->name ?? 'No User Role' }}
                                </p>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Department</label>
                                <p class="text-muted bg-light p-2 rounded border">
                                    {{ optional(backpack_user()->department)->department_name ?? 'No Department Assigned' }}
                                </p>
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Division</label>
                                <p class="text-muted bg-light p-2 rounded border">
                                    {{ optional(backpack_user()->division)->division_name ?? 'No Division Assigned' }}
                                </p>
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Profile Avatar</label>

                                <div class="d-flex align-items-center mb-3">
                                    <img
                                        src="{{ $user->getAvatarUrl() }}"
                                        alt="User Avatar"
                                        style="width: 120px; height: 120px; object-fit: contain; background: #fff; border-radius: 50%; border: 1px solid #ddd;"
                                    >
                                </div>

                                <input
                                    class="form-control"
                                    type="file"
                                    name="avatar"
                                    accept="image/*"
                                >

                                <small class="form-text text-muted">
                                    Allowed: JPG, JPEG, PNG, WEBP. Max: 2MB.
                                </small>

                                @if(!empty($user->avatar_url))
                                    <div class="form-check mt-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="remove_avatar"
                                            id="remove_avatar"
                                            value="1"
                                        >
                                        <label class="form-check-label" for="remove_avatar">
                                            Remove current avatar
                                        </label>
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Skills</label>

                                {{-- Visual Tag Container --}}
                                <div id="skills-container" class="form-control" style="height: auto; min-height: 45px; display: flex; flex-wrap: wrap; gap: 5px; align-items: center; cursor: text;" onclick="document.getElementById('skill-input').focus()">

                                    {{-- The invisible input field for typing --}}
                                    <input type="text" id="skill-input" placeholder="Type a skill & press Enter" style="border: none; outline: none; flex-grow: 1; min-width: 200px; background: transparent;">
                                </div>
                                <small class="form-text text-muted">Type your custom skill and press <strong>Enter</strong> or a <strong>Comma</strong> to add it.</small>

                                {{-- Hidden container that actually submits the array to Laravel --}}
                                <div id="hidden-skills-inputs">
                                    @php
                                        $currentSkills = old('skills', $user->skills ?? []);
                                        if (is_string($currentSkills)) {
                                            $currentSkills = json_decode($currentSkills, true) ?? [];
                                        }
                                    @endphp

                                    @foreach($currentSkills as $skill)
                                        <input type="hidden" name="skills[]" value="{{ $skill }}">
                                    @endforeach
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="la la-save"></i> {{ trans('backpack::base.save') }}</button>
                        <a href="{{ backpack_url() }}" class="btn">{{ trans('backpack::base.cancel') }}</a>
                    </div>
                </div>

            </form>
        </div>

        {{-- CHANGE PASSWORD FORM
        <div class="col-lg-10">
            <form class="form" action="{{ route('backpack.account.password') }}" method="post">

                {!! csrf_field() !!}

                <div class="card padding-10">

                    <div class="card-header">
                        {{ trans('backpack::base.change_password') }}
                    </div>

                    <div class="card-body backpack-profile-form bold-labels">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                @php
                                    $label = trans('backpack::base.old_password');
                                    $field = 'old_password';
                                @endphp
                                <label class="required">{{ $label }}</label>
                                <input autocomplete="new-password" required class="form-control" type="password" name="{{ $field }}" id="{{ $field }}" value="">
                            </div>

                            <div class="col-md-4 form-group">
                                @php
                                    $label = trans('backpack::base.new_password');
                                    $field = 'new_password';
                                @endphp
                                <label class="required">{{ $label }}</label>
                                <input autocomplete="new-password" required class="form-control" type="password" name="{{ $field }}" id="{{ $field }}" value="">
                            </div>

                            <div class="col-md-4 form-group">
                                @php
                                    $label = trans('backpack::base.confirm_password');
                                    $field = 'confirm_password';
                                @endphp
                                <label class="required">{{ $label }}</label>
                                <input autocomplete="new-password" required class="form-control" type="password" name="{{ $field }}" id="{{ $field }}" value="">
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="la la-save"></i> {{ trans('backpack::base.change_password') }}</button>
                            <a href="{{ backpack_url() }}" class="btn">{{ trans('backpack::base.cancel') }}</a>
                    </div>

                </div>

            </form>
        </div> --}}

        {{-- NOTIFICATIONS SETTINGS --}}
        <div class="col-lg-10">
            <form class="form" action="{{ route('backpack.account.notifications') }}" method="post">
                {!! csrf_field() !!}

                <div class="card padding-10">
                    <div class="card-header">
                        Notification Settings
                    </div>

                    <div class="card-body backpack-profile-form bold-labels">
                        <div class="row">
                            @if(backpack_user()->hasAnyRole(['admin', 'dept_head', 'div_head']))
                                <div class="col-md-6 form-group">
                                    <div class="custom-control custom-switch">
                                        <input
                                            type="checkbox"
                                            class="custom-control-input"
                                            id="notify_ticket_created"
                                            name="notify_ticket_created"
                                            {{ old('notify_ticket_created', $user->notify_ticket_created) ? 'checked' : '' }}
                                        >
                                        <label class="custom-control-label" for="notify_ticket_created">
                                            Notify me when a ticket is created
                                        </label>
                                    </div>
                                </div>
                            @endif

                            <div class="col-md-6 form-group">
                                <div class="custom-control custom-switch">
                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="notify_ticket_assigned"
                                        name="notify_ticket_assigned"
                                        {{ old('notify_ticket_assigned', $user->notify_ticket_assigned) ? 'checked' : '' }}
                                    >
                                    <label class="custom-control-label" for="notify_ticket_assigned">
                                        Notify me when a ticket is assigned
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6 form-group">
                                <div class="custom-control custom-switch">
                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="notify_ticket_status_changed"
                                        name="notify_ticket_status_changed"
                                        {{ old('notify_ticket_status_changed', $user->notify_ticket_status_changed) ? 'checked' : '' }}
                                    >
                                    <label class="custom-control-label" for="notify_ticket_status_changed">
                                        Notify me when ticket status changes
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6 form-group">
                                <div class="custom-control custom-switch">
                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="notify_ticket_commented"
                                        name="notify_ticket_commented"
                                        {{ old('notify_ticket_commented', $user->notify_ticket_commented) ? 'checked' : '' }}
                                    >
                                    <label class="custom-control-label" for="notify_ticket_commented">
                                        Notify me when a ticket gets a new comment
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="la la-save"></i> Save
                        </button>
                        <a href="{{ backpack_url() }}" class="btn">
                            {{ trans('backpack::base.cancel') }}
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('after_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const skillInput = document.getElementById('skill-input');
        const container = document.getElementById('skills-container');
        const hiddenInputsContainer = document.getElementById('hidden-skills-inputs');

        // Render existing tags on page load
        const existingInputs = hiddenInputsContainer.querySelectorAll('input');
        existingInputs.forEach(input => {
            renderBadge(input.value);
        });

        // Listen for Enter or Comma keys
        skillInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault(); // Stop form from submitting early

                let val = this.value.trim().toLowerCase(); // Clean input
                if(val !== '') {
                    // Check for duplicates
                    if(!hiddenInputsContainer.querySelector('input[value="' + val + '"]')) {
                        renderBadge(val);
                        addHiddenInput(val);
                    }
                    this.value = ''; // Clear input
                }
            }
        });

        // Allow Backspace to delete the last tag if input is empty
        skillInput.addEventListener('keyup', function(e) {
            if (e.key === 'Backspace' && this.value === '') {
                const badges = container.querySelectorAll('.skill-badge');
                if(badges.length > 0) {
                    const lastBadge = badges[badges.length - 1];
                    const skillName = lastBadge.getAttribute('data-skill');
                    removeTag(lastBadge, skillName);
                }
            }
        });

        function renderBadge(skillName) {
            const badge = document.createElement('span');
            badge.className = 'badge badge-primary mr-1 mb-1 d-flex align-items-center skill-badge';
            badge.style.fontSize = '14px';
            badge.style.padding = '6px 12px';
            badge.setAttribute('data-skill', skillName);

            // X button to remove
            badge.innerHTML = skillName + ' <i class="la la-times ml-2" style="cursor:pointer;" onclick="removeTag(this.parentElement, \'' + skillName + '\')"></i>';

            // Insert badge right before the typing input field
            container.insertBefore(badge, skillInput);
        }

        function addHiddenInput(skillName) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'skills[]';
            hiddenInput.value = skillName;
            hiddenInputsContainer.appendChild(hiddenInput);
        }

        // Global function to remove visual badge AND hidden input
        window.removeTag = function(badgeElement, skillName) {
            badgeElement.remove();
            const hiddenInput = hiddenInputsContainer.querySelector('input[value="' + skillName + '"]');
            if (hiddenInput) hiddenInput.remove();
        }
    });
</script>
@endsection
