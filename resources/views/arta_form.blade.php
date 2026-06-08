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
    <link rel="stylesheet" href="{{ asset('public-assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public-assets/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public-assets/css/css2.css') }}">
    <link rel="stylesheet" href="{{ asset('arta-assets/css/arta.css') }}">
    <link rel="manifest" href="{{ asset('manifest-survey.json') }}">
    <title>ARTA Client Satisfaction Form</title>
</head>
<body>
    <video id="background-video" autoplay loop muted playsinline preload="auto" style="position: fixed; top: 0; left: 0; min-width: 100%; min-height: 100%; z-index: -1; object-fit: cover;">
        <source src="{{ asset('assets/blue.webm') }}" type="video/webm">
        <source src="{{ asset('assets/blue.mp4') }}" type="video/mp4">
    </video>

    <div class="survey-shell">
        <header class="survey-header" id="surveyHeader"></header>

        <form action="{{ route('arta.submit') }}" method="POST" class="survey-form w-100">
            @csrf

            <div id="surveyCarousel" class="carousel slide" data-bs-ride="false" data-bs-interval="false">
                <div class="carousel-inner">

                    {{-- Slide 1: Front--}}
                    <div class="carousel-item active">
                        <div class="question-slide">
                            <div class="center-logo-container mb-4 d-flex justify-content-center w-100">
                                <picture>
                                    <source type="image/webp" srcset="{{ asset('assets/logo-with-seal.webp') }}">
                                    <img src="{{ asset('assets/logo-with-seal.webp') }}" alt="HRDO Logo" loading="lazy" style="max-width: 150px;">
                                </picture>
                            </div>
                            <h4 class="slide-title" style="color: red">ANTI-RED TAPE AUTHORITY CLIENT SATISFACTION FORM</h4>
                            <div class="instructions text-center mb-4 border-0 bg-transparent p-0">
                                <p class="text-muted" style="font-size: 1rem; line-height: 1.5;">This Client Satisfaction Measurement (CSM) tracks the customer experience of government offices. Your feedback on your <strong>recently concluded transaction</strong> will help this office provide a better service. Personal information shared will be kept confidential and you always have the option not to answer this form.</p>
                            </div>
                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Start Survey</button>
                        </div>
                    </div>

                    {{-- Slide 2: Client Type --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h3 class="slide-title">Client Type</h3>
                            <div class="choice-container">
                                <div>
                                    <input type="radio" class="btn-check" id="client_citizen" name="client_type" value="Citizen" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="client_citizen">👤 Citizen</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="client_business" name="client_type" value="Business" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="client_business">🏢 Business</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="client_gov" name="client_type" value="Government" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="client_gov">🏛️ Government (Employee or agency)</label>
                                </div>
                            </div>
                            <div class="nav-buttons">
                                <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 3: Sex --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h3 class="slide-title">Sex</h3>
                            <div class="choice-container">
                                <div>
                                    <input type="radio" class="btn-check" id="sex_male" name="sex" value="Male" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="sex_male">Male</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="sex_female" name="sex" value="Female" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="sex_female">Female</label>
                                </div>
                            </div>
                            <div class="nav-buttons">
                                <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 4: Age --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h3 class="slide-title">Age</h3>
                            <input
                                type="number"
                                id="age"
                                name="age"
                                class="form-control custom-input text-center"
                                placeholder="Enter your age"
                                min="1"
                                max="150"
                                step="1"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                            >
                            <div class="nav-buttons">
                                <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 5: Region --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h3 class="slide-title">Region of Residence</h3>

                            <select name="region" id="region" class="form-select custom-input text-center" required>
                                <option value="" disabled selected>Select your region</option>
                                @foreach(\App\Models\ArtaSurvey::REGIONS as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>

                            <div class="nav-buttons">
                                <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 6: Service --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h3 class="slide-title">Service Availed</h3>
                            <input type="text" name="service_availed" class="form-control custom-input text-center" placeholder="What service did you avail?">
                            <div class="nav-buttons">
                                <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 7: CC1 --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <div class="instructions">
                                <strong>INSTRUCTIONS:</strong> Please select your answer on the Citizen’s Charter (CC) questions. The Citizen’s Charter is an official document that reflects the services of a
                                government agency/office including its requirements, fees, and processing times among others.
                            </div>
                            <h3 class="slide-title" style="font-size: 1.3rem;">CC1: Which of the following best describes your awareness of a CC?</h3>

                            <div class="choice-container">
                                <div>
                                    <input type="radio" class="btn-check" id="cc1_1" name="cc1" value="1" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc1_1">1. I know what a CC is and I saw this office’s CC.</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc1_2" name="cc1" value="2" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc1_2">2. I know what a CC is but I did NOT see this office’s CC.</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc1_3" name="cc1" value="3" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc1_3">3. I learned of the CC only when I saw this office’s CC.</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc1_4" name="cc1" value="4" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc1_4">4. I do not know what a CC is and I did not see one in this office. (Answer ‘N/A’ on CC2 and CC3)</label>
                                </div>
                            </div>
                            <div class="nav-buttons">
                                <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 8: CC2 --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h3 class="slide-title" style="font-size: 1.3rem;">CC2: If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was …?</h3>
                            <div class="choice-container">
                                <div>
                                    <input type="radio" class="btn-check" id="cc2_1" name="cc2" value="1" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc2_1">1. Easy to see</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc2_2" name="cc2" value="2" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc2_2">2. Somewhat easy to see</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc2_3" name="cc2" value="3" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc2_3">3. Difficult to see</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc2_4" name="cc2" value="4" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc2_4">4. Not visible at all</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc2_5" name="cc2" value="5" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc2_5">5. Not Applicable</label>
                                </div>
                            </div>
                            <div class="nav-buttons">
                                <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 9: CC3 --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h3 class="slide-title" style="font-size: 1.3rem;">CC3: If aware of CC (answered codes 1-3 in CC1), how much did the CC help you in your transaction?</h3>
                            <div class="choice-container">
                                <div>
                                    <input type="radio" class="btn-check" id="cc3_1" name="cc3" value="1" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc3_1">1. Helped very much</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc3_2" name="cc3" value="2" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc3_2">2. Somewhat helped</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc3_3" name="cc3" value="3" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc3_3">3. Did not help</label>
                                </div>
                                <div>
                                    <input type="radio" class="btn-check" id="cc3_4" name="cc3" value="4" autocomplete="off">
                                    <label class="btn btn-outline-primary w-100" for="cc3_4">4. Not Applicable</label>
                                </div>
                            </div>
                            <div class="nav-buttons">
                                <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slides 10-18: SQD Questions Array --}}
                    @foreach(\App\Models\ArtaSurvey::SQD_QUESTIONS as $sqd_no => $question_text)
                    <div class="carousel-item">
                        <div class="question-slide">
                            <div class="instructions mb-4">
                                <strong>INSTRUCTIONS:</strong> Please tap on the emoji that best corresponds to your answer.
                            </div>
                            <h3 class="slide-title" style="font-size: 1.4rem;">{{ $question_text }}</h3>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, '{{ $sqd_no}}', 'Strongly Agree', {{ $loop->last ? 'true' : 'false' }})">
                                    <input type="radio" name="{{ $sqd_no}}" value="Strongly Agree" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, '{{ $sqd_no}}', 'Agree', {{ $loop->last ? 'true' : 'false' }})">
                                    <input type="radio" name="{{ $sqd_no}}" value="Agree" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, '{{ $sqd_no}}', 'Neither', {{ $loop->last ? 'true' : 'false' }})">
                                    <input type="radio" name="{{ $sqd_no}}" value="Neither" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, '{{ $sqd_no}}', 'Disagree', {{ $loop->last ? 'true' : 'false' }})">
                                    <input type="radio" name="{{ $sqd_no}}" value="Disagree" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Disagree</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, '{{ $sqd_no}}', 'Strongly Disagree', {{ $loop->last ? 'true' : 'false' }})">
                                    <input type="radio" name="{{ $sqd_no}}" value="Strongly Disagree" class="visually-hidden">
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Disagree</span>
                                </label>
                                <label class="rating-option" onclick="selectRating(this, '{{ $sqd_no}}', 'N/A', {{ $loop->last ? 'true' : 'false' }})">
                                    <input type="radio" name="{{ $sqd_no}}" value="N/A" class="visually-hidden">
                                    <span class="na-text">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <div class="nav-buttons">
                                <button type="button" class="btn btn-outline-secondary go-back-btn-large" onclick="prevSlide()">Back</button>
                                @if(!$loop->last)
                                    <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                                @else
                                    <button type="button" class="btn btn-success go-back-btn-large" onclick="submitForm()">Submit Form</button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach

                    {{-- Slide 19: QR --}}
                    <div class="carousel-item" id="qr-timeout-slide">
                        <div class="question-slide text-center">
                            @if(session('success'))
                                <div id="success-message" class="alert alert-success fw-bold mb-4" role="alert">
                                    {{ session('success') }}
                                </div>
                            @endif

                            <h3 class="slide-title">Thank you! Scan the QR code for comments and suggestions.</h3>

                            <div class="qr-code-container my-4 bg-white p-3 d-inline-block rounded-4 shadow-sm">
                                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::backgroundColor(255, 255, 255)->size(200)->generate('https://forms.gle/Tvmm2WmjHGNqteUD9') !!}
                            </div>

                            <div class="mt-4">
                                <button type="button" class="btn btn-primary go-back-btn-large w-100" onclick="window.location.reload()">Another Survey</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>

        @if ($errors->any())
            <div class="alert alert-danger" style="position: absolute; top: 10px; left: 10px; z-index: 9999;">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="carousel-indicators">
            <button type="button" data-bs-target="#surveyCarousel" class="active" aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 3"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 4"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 5"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 6"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 7"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 8"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 9"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 10"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 11"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 12"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 13"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 14"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 15"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 16"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 17"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 18"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 19"></button>
        </div>
    </div>

    <script src="{{ asset('public-assets/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('public-assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('public-assets/js/browser@4.js') }}"></script>
    <script src="{{ asset('arta-assets/js/arta.js') }}"></script>
</body>
</html>
