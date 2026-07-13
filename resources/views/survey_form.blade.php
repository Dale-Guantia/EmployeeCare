<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0056b3">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="HRDO Survey">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="{{ asset('assets/icons/icon-192x192.png') }}">
    <link rel="preload" as="image" href="{{ asset('assets/logo-with-seal.webp') }}" type="image/webp">
    <link rel="preload" as="image" href="{{ asset('assets/blue.webp') }}" type="image/webp">
    <link rel="stylesheet" href="{{ asset('public-assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public-assets/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public-assets/css/css2.css') }}">
    <link rel="stylesheet" href="{{ asset('survey-assets/css/survey.css') }}">
    <link rel="manifest" href="{{ asset('manifest-survey.json') }}">
    <title>Customer Satisfaction Survey</title>
</head>
<body>
    <button id="installSurveyApp" type="button" class="btn btn-warning is-hidden">Install App</button>

    <video id="background-video" autoplay loop muted playsinline preload="metadata" poster="{{ asset('assets/blue.webp') }}">
        <source src="{{ asset('assets/blue.webm') }}" type="video/webm">
        <source src="{{ asset('assets/blue.mp4') }}" type="video/mp4">
        <img src="{{ asset('assets/blue.webp') }}" alt="Background">
        <img src="{{ asset('assets/blue.jpg') }}" alt="Background">
        Your browser does not support the video tag.
    </video>

    <div class="survey-shell">
        <header class="survey-header" id="surveyHeader">
            <div class="center-logo-container d-flex justify-content-center w-100">
                <picture>
                    <source type="image/webp" srcset="{{ asset('assets/logo-with-seal.webp') }}">
                    <img src="{{ asset('assets/logo-with-seal.webp') }}" alt="HRDO Logo" width="522" height="133" decoding="async">
                </picture>
            </div>

            <h5 class="survey-subtitle">CITY HUMAN RESOURCE DEVELOPMENT OFFICE</h5>
            <h1 class="survey-title">Customer Satisfaction Survey</h1>
        </header>

        <form action="{{ route('survey.submit') }}" method="POST" class="survey-form">
            @csrf

            <div id="surveyCarousel" class="carousel slide" data-bs-ride="false" data-bs-interval="false">
                <div class="carousel-inner">

                    {{-- Slide 1: Division --}}
                    <div class="carousel-item active">
                        <div class="question-slide question-slide--division">
                            <h3 class="slide-title">Select Division / Pumili ng Dibisyon:</h3>

                            <div class="division-buttons-grid">
                                <button type="button" class="btn btn-it division-btn" data-division-id="2">INFORMATION TECHNOLOGY</button>
                                <button type="button" class="btn btn-admin division-btn" data-division-id="3">ADMINISTRATIVE</button>
                                <button type="button" class="btn btn-records division-btn" data-division-id="5">RECORDS</button>
                                <button type="button" class="btn btn-payroll division-btn" data-division-id="4">PAYROLL</button>
                                <button type="button" class="btn btn-claims division-btn" data-division-id="6">CLAIMS &amp; BENEFITS</button>
                                <button type="button" class="btn btn-rsp division-btn" data-division-id="7">RSP</button>
                                <button type="button" class="btn btn-ld division-btn" data-division-id="8">LEARNING &amp; DEVELOPMENT</button>
                                <button type="button" class="btn btn-pm division-btn" data-division-id="9">PERFORMANCE MANAGEMENT</button>
                                <button type="button" class="btn btn-all division-btn active" data-division-id="all">ALL DIVISIONS</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 2: Staff --}}
                    <div class="carousel-item" id="staff-selection-slide">
                        <div class="question-slide">
                            <h3 class="slide-title slide-title--blue">Attended by / Inasikaso ni:</h3>

                            <div class="selection-wrapper">
                                <button type="button" class="service-nav-btn prev-staff-btn" onclick="changeStaffPage(-1)">
                                    <i class="fas fa-chevron-left"></i>
                                </button>

                                <div class="selection-content">
                                    <div id="staff-grid-paginated">
                                        @foreach($staffs as $staff)
                                            <div class="text-center staff-item is-hidden" data-division-id="{{ $staff->division_id }}">
                                                <label class="w-100" style="cursor: pointer;">
                                                    <input
                                                        type="radio"
                                                        name="user_id"
                                                        value="{{ $staff->id }}"
                                                        id="staff-{{ $staff->id }}"
                                                        data-division-id="{{ $staff->division_id }}"
                                                        class="visually-hidden"
                                                        required
                                                    >

                                                    <picture>
                                                        @if ($staff->getAvatarWebpUrl())
                                                            <source srcset="{{ $staff->getAvatarWebpUrl() }}" type="image/webp">
                                                        @endif

                                                        <img
                                                            src="{{ $staff->getAvatarUrl() }}"
                                                            alt="{{ $staff->name }}'s profile picture"
                                                            class="staff-avatar"
                                                            loading="lazy"
                                                            decoding="async"
                                                        >
                                                    </picture>
                                                </label>

                                                <span class="staff-name mt-2">{{ $staff->name }}</span>
                                                <span class="staff-nickname">"{{ $staff->nickname }}"</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <button type="button" class="service-nav-btn next-staff-btn" onclick="changeStaffPage(1)">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>

                            <div id="staff-page-indicator" class="page-indicator"></div>

                            <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 3: Service --}}
                    <div class="carousel-item service-slide">
                        <div class="question-slide">
                            <h3 class="slide-title slide-title--red">Service Received / Serbisyong Natanggap:</h3>

                            <div class="selection-wrapper">
                                <button type="button" class="service-nav-btn prev-service-btn" onclick="changeServicePage(-1)">
                                    <i class="fas fa-chevron-left"></i>
                                </button>

                                <div class="selection-content">
                                    <div id="service-grid-paginated">
                                        @foreach($services as $index => $service)
                                            @php $color = $colors[$index % count($colors)]; @endphp

                                            <div
                                                class="service-item text-center is-hidden"
                                                data-division-id="{{ $service->division_id }}"
                                                data-service-id="{{ $service->id }}"
                                            >
                                                <label class="w-100" style="cursor: pointer;">
                                                    <input type="radio" name="issue_id" value="{{ $service->id }}" class="visually-hidden" required>

                                                    <div class="service-icon-box" style="background-color: {{ $color }};">
                                                        <i class="{{ $service->icon }}"></i>
                                                    </div>

                                                    <span class="service-name">{{ $service->issue_description }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <button type="button" class="service-nav-btn next-service-btn" onclick="changeServicePage(1)">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>

                            <div id="service-page-indicator" class="page-indicator"></div>

                            <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 4: Timeliness --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <h2 class="question-heading question-heading--orange">BILIS NG SERBISYO (TIMELINESS)</h2>
                            <p class="mb-1">Ang oras ng pagproseso ng inyong application/request ay akma o mas mabilis kaysa sa inaasahan.</p>
                            <p class="text-muted fst-italic" style="font-size: 0.95rem;">(The time taken to process my application/request was reasonable or faster than expected.)</p>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Very Satisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😊</span>
                                    <span class="emoji-rating-text">Satisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙁</span>
                                    <span class="emoji-rating-text">Dissatisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😞</span>
                                    <span class="emoji-rating-text">Very Dissatisfied</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 5: Client Handling --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <h2 class="question-heading question-heading--pink">PAKIKITUNGO SA KLIYENTE (CLIENT HANDLING)</h2>
                            <p class="mb-1">Magalang at propesyonal ang pakikitungo ng kawani na nagbigay ng serbisyo.</p>
                            <p class="text-muted fst-italic" style="font-size: 0.95rem;">(The staff member was courteous and maintained a professional demeanor throughout the transaction.)</p>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'handling_rating', 'Very Satisfied')">
                                    <input type="radio" name="handling_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Very Satisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'handling_rating', 'Satisfied')">
                                    <input type="radio" name="handling_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😊</span>
                                    <span class="emoji-rating-text">Satisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'handling_rating', 'Dissatisfied')">
                                    <input type="radio" name="handling_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙁</span>
                                    <span class="emoji-rating-text">Dissatisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'handling_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="handling_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😞</span>
                                    <span class="emoji-rating-text">Very Dissatisfied</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 6: Quality --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <h2 class="question-heading question-heading--blue">KALIDAD NG SERBISYO (QUALITY OF SERVICE)</h2>
                            <p class="mb-1">Ang serbisyo o dokumentong natanggap ko ay tumpak, kompleto, at walang kamalian.</p>
                            <p class="text-muted fst-italic" style="font-size: 0.95rem;">(The service or document I received was accurate, complete, and free of errors.)</p>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'quality_rating', 'Very Satisfied', false)">
                                    <input type="radio" name="quality_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Very Satisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'quality_rating', 'Satisfied', false)">
                                    <input type="radio" name="quality_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😊</span>
                                    <span class="emoji-rating-text">Satisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'quality_rating', 'Dissatisfied', false)">
                                    <input type="radio" name="quality_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙁</span>
                                    <span class="emoji-rating-text">Dissatisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'quality_rating', 'Very Dissatisfied', false)">
                                    <input type="radio" name="quality_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😞</span>
                                    <span class="emoji-rating-text">Very Dissatisfied</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 7: Overall --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <h2 class="question-heading question-heading--green">OVERALL SATISFACTION</h2>
                            <p class="mb-1">Lubos akong nasiyahan sa serbisyong aking natanggap.</p>
                            <p class="text-muted fst-italic" style="font-size: 0.95rem;">(I am satisfied with the service that I received.)</p>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'overall_rating', 'Very Satisfied', true)">
                                    <input type="radio" name="overall_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Very Satisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'overall_rating', 'Satisfied', true)">
                                    <input type="radio" name="overall_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😊</span>
                                    <span class="emoji-rating-text">Satisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'overall_rating', 'Dissatisfied', true)">
                                    <input type="radio" name="overall_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙁</span>
                                    <span class="emoji-rating-text">Dissatisfied</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, 'overall_rating', 'Very Dissatisfied', true)">
                                    <input type="radio" name="overall_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😞</span>
                                    <span class="emoji-rating-text">Very Dissatisfied</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 8: QR --}}
                    <div class="carousel-item" id="qr-timeout-slide">
                        <div class="question-slide question-slide--qr">
                            @if(session('success'))
                                <div id="success-message" class="alert alert-success success-message fw-bold px-3" role="alert">
                                    {{ session('success') }}
                                </div>
                            @endif

                            <h3 class="slide-title slide-title--blue mt-3">Scan the QR code to fill out the comments and suggestions form</h3>

                            <div class="qr-code-container bg-white p-4 d-inline-block rounded-4 shadow-sm my-3">
                                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::backgroundColor(255, 255, 255, 0)->size(220)->generate('https://forms.gle/Tvmm2WmjHGNqteUD9') !!}
                            </div>

                            <div class="mt-2">
                                <button type="button" class="btn btn-primary go-back-btn-large px-5" onclick="window.location.reload()">Rate Again</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>

        <div class="carousel-indicators">
            <button type="button" data-bs-target="#surveyCarousel" class="active" aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 3"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 4"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 5"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 6"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 7"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 8"></button>
        </div>
    </div>

    <script src="{{ asset('public-assets/js/jquery-3.6.0.min.js') }}" defer></script>
    <script src="{{ asset('public-assets/js/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('survey-assets/js/survey.js') }}" defer></script>
</body>
</html>
