<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Satisfaction Survey</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Noto+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    {{-- @laravelPWA --}}
</head>
<body>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            color: #000;
            font-family: "Bricolage Grotesque", sans-serif;
        }
        #background-video {
            position: fixed;
            top: 0;
            left: 0;
            min-width: 100%;
            min-height: 100%;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }
        .container {
            min-height: 100vh;
            padding: 1rem;
            position: relative;
            z-index: 10;
        }
        h1, h2, h3, h4, h5, p {
            text-align: center;
            padding: 0;
            margin: 0;
        }
        h2 {
            padding-top: 50px;
            font-weight: 700;
        }
        h3 {
            font-weight: 600;
            font-size: 2.5rem;
        }
        p {
            font-size: 1.9rem;
        }
        .center-logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding: 20px;
            flex-wrap: wrap;
        }
        .center-logo-container picture img {
            max-width: 300px;
            width: 100%;
            height: auto;
            display: block;
        }
        .carousel-item {
            min-height: 400px;
        }
        .question-slide {
            padding: 50px 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .carousel-control-prev, .carousel-control-next {
            display: none;
        }
        .carousel-indicators [data-bs-target] {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #007bff;
        }
        .staff-carousel-container {
            position: relative;
            width: 100%;
            max-width: 90%;
            margin: 0 auto;
            display: flex;
            align-items: center;
        }
        .emoji-icon {
            font-size: 11.5rem;
        }
        .emoji-rating-text {
            margin-top: -35px;
            font-size: 2rem;
            font-weight: 500;
            color: #333333;
            transition: all 0.2s ease-in-out;
        }
        /* --- RATING OPTION STYLES --- */
        .rating-option {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            cursor: pointer;
            padding: 10px;
            transition: transform 0.2s ease-in-out;
        }
        .rating-option:hover {
            transform: scale(1.1);
        }
        .rating-option.active {
            transform: scale(1.15);
        }
        .rating-option.active .emoji-rating-text {
            font-weight: 700;
        }
        .btn {
            min-width: 120px;
        }
        .go-back-btn-large {
            padding: 15px 40px !important;
            font-size: 1.5rem !important;
            border-radius: 12px;
        }
        .staff-scroll-container {
            position: relative;
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            overflow: visible;
            padding: 0;
        }
        .staff-scroll-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            justify-items: center;
            align-items: start;
            gap: 70px 50px;
            overflow-y: scroll;
            scroll-snap-type: y mandatory;
            max-height: 450px;
            width: 100%;
            overflow-x: visible;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .staff-item {
            text-align: center;
            transition: transform 0.25s, box-shadow 0.25s;
            scroll-snap-align: start;
            display: inline-block;
            cursor: pointer;
            flex-shrink: 0;
            min-width: 340px;
            max-width: 340px;
        }
        .staff-avatar {
            width: 300px;
            height: 300px;
            border-radius: 50%;
            object-fit: contain;
            background: transparent;
            border: 3px solid transparent;
            cursor: pointer;
        }
        .staff-avatar.selected {
            border-color: #007bff;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        }
        .staff-name {
            display: block;
            font-weight: 500;
            font-size: 1.5rem;
        }
        .staff-nickname {
            display: block;
            font-weight: 1000;
            font-size: 2.5rem;
        }
        .staff-scroll-panel::-webkit-scrollbar {
            width: 8px;
        }
        .staff-scroll-panel::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }
        .btn.btn-primary {
            border-radius: 25px;
        }
        .filter-btn {
            opacity: 0.6;
            transition: opacity 0.2s;
        }
        .filter-btn.active {
            opacity: 1;
            font-weight: bold;
            border: 2px solid #fff;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        /* --- NEW SERVICE PAGINATION STYLES (4 Columns x 2 Rows) --- */

        .service-carousel-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* The Grid Container */
        #service-grid-paginated {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* Force 4 columns */
            grid-template-rows: auto auto; /* Force 2 rows height behavior */
            gap: 20px;
            justify-items: center;
            width: 100%;
            min-height: 500px; /* Fixed height to prevent jumping */
            padding: 10px;
        }

        .service-item {
            width: 100%;
            max-width: 250px; /* Max width inside grid cell */
            padding: 5px;
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
            box-sizing: border-box;
        }

        .service-icon-box {
            color: #000;
            width: 110px;
            height: 110px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            cursor: pointer;
            border: #000 2px solid;
        }

        .service-item:hover {
            transform: scale(1.1);
        }

        .service-item.selected {
            transform: scale(1.1);
        }

        .service-item.selected .service-name {
            font-weight: 700;
        }

        .service-name {
            font-size: 1.5rem;
            font-weight: 500;
            white-space: normal;
            display: block;
        }

        /* Navigation Buttons */
        .service-nav-btn {
            background-color: rgba(0, 0, 0, 0.05);
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
            z-index: 10;
        }

        .service-nav-btn:hover {
            background-color: rgba(0, 0, 0, 0.2);
            transform: scale(1.1);
        }

        .service-nav-btn:disabled,
        .service-nav-btn.disabled-visual {
            opacity: 0.1;
            cursor: default;
            pointer-events: none;
        }

        /* --- Division Buttons Grid --- */
        .division-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 10px;
        }
        .division-buttons-grid .division-btn {
            width: 100%;
            padding: 25px 30px;
            font-size: 1.5rem;
            text-align: center;
            border-radius: 25px;
        }
        .division-buttons-grid .btn {
            color: #000;
            border: #000 2px solid;
            font-weight: 600;
            transition: background-color 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .division-buttons-grid .btn-all { background-color: #ff7572; }
        .division-buttons-grid .btn-it { background-color: #b56bff; }
        .division-buttons-grid .btn-admin { background-color: #3496ff; }
        .division-buttons-grid .btn-records { background-color: #57caff; }
        .division-buttons-grid .btn-payroll { background-color: #1dffb0; }
        .division-buttons-grid .btn-claims { background-color: #58fa5d; }
        .division-buttons-grid .btn-rsp { background-color: #e3f85d; }
        .division-buttons-grid .btn-ld { background-color: #ffd152; }
        .division-buttons-grid .btn-pm { background-color: #ff9a42; }
    </style>

    <video id="background-video" autoplay loop muted playsinline preload="auto">
        <source src="{{ asset('storage/assets/blue.webm') }}" type="video/webm">
        <source src="{{ asset('storage/assets/blue.mp4') }}" type="video/mp4">
        <img src="{{ asset('storage/assets/blue.webp') }}" alt="Background" />
        <img src="{{ asset('storage/assets/blue.jpg') }}" alt="Background" />
        Your browser does not support the video tag.
    </video>

    <div class="container">
        <div class="center-logo-container" preload="auto">
            <picture>
                <source type="image/webp" srcset="{{ asset('storage/assets/logo-with-seal.webp') }}">
                <img src="{{ asset('storage/assets/logo-with-seal.webp') }}" alt="HRDO Logo" loading="lazy">
            </picture>
        </div>
        <h5 style="color: red">HUMAN RESOURCE DEVELOPMENT division</h5>
        <h1 style="color: #0056b3">Customer Satisfaction Survey</h1>

        <form action="{{ route('survey.submit') }}" method="POST">
            @csrf

            <div id="surveyCarousel" class="carousel slide" data-bs-ride="false" data-bs-interval="false">
                <div class="carousel-inner">

                    {{-- SLIDE 1: Division Selection --}}
                    {{-- <div class="carousel-item active">
                        <div class="question-slide">
                            <h3 style="padding-bottom: 10px;">Start Survey</h3>
                            <button type="button" class="btn btn-primary mt-3" onclick="nextSlide()">Next</button>
                        </div>
                    </div> --}}
                    {{-- END: SLIDE 1 --}}

                    {{-- SLIDE 1: Division Selection --}}
                        {{-- <div class="carousel-item">
                            <div class="question-slide">
                                <div>
                                    <label>Kasarian: </label>
                                    <input type="checkbox" id="babae" name="babae" value="Babae">
                                    <label for="babae"> Babae</label>
                                    <input type="checkbox" id="lalaki" name="lalaki" value="Lalaki">
                                    <label for="lalaki"> Lalake</label>
                                </div>
                                <div>
                                    <label>Age: </label>
                                    <input id="age" type="number" name="age" min="0" max="150" placeholder="Enter your age">
                                </div>
                                <div>
                                    <label>Uri ng kliyente: </label>
                                    <input type="checkbox" id="client_type1" name="client_type1" value="Pasigueño">
                                    <label for="client_type1">Pasigueño</label>
                                    <input type="checkbox" id="client_type2" name="client_type2" value="Non-Pasigueño">
                                    <label for="client_type2">Non-Pasigueño</label>
                                    <input type="checkbox" id="client_type3" name="client_type3" value="City Government Employee">
                                    <label for="client_type3">City Government Employee</label>
                                </div>
                                <button type="button" class="btn btn-primary mt-3 go-back-btn-large" onclick="prevSlide()">Go Back</button>
                            </div>
                        </div> --}}
                    {{-- END: SLIDE 1 --}}

                    {{-- SLIDE 1: Division Selection --}}
                    <div class="carousel-item active">
                        <div class="question-slide">
                            <h3 style="padding-bottom: 10px;">Select Division / Pumili ng Dibisyon:</h3>
                            <div class="filter-buttons division-buttons-grid">
                                <button type="button" class="btn btn-it mb-3 division-btn" data-division-id="2">INFORMATION TECHNOLOGY</button>
                                <button type="button" class="btn btn-admin mb-3 division-btn" data-division-id="3">ADMINISTRATIVE</button>
                                <button type="button" class="btn btn-records mb-3 division-btn" data-division-id="5">RECORDS</button>
                                <button type="button" class="btn btn-payroll mb-3 division-btn" data-division-id="4">PAYROLL</button>
                                <button type="button" class="btn btn-claims mb-3 division-btn" data-division-id="6">CLAIMS & BENEFITS</button>
                                <button type="button" class="btn btn-rsp mb-3 division-btn" data-division-id="7">RSP</button>
                                <button type="button" class="btn btn-ld mb-3 division-btn" data-division-id="8">LEARNING & DEVELOPMENT</button>
                                <button type="button" class="btn btn-pm mb-3 division-btn" data-division-id="9">PERFORMANCE MANAGEMENT</button>
                                <button type="button" class="btn btn-all mb-3 division-btn active" data-division-id="all">ALL DIVISIONS</button>
                            </div>
                            {{-- <button type="button" class="btn btn-primary mt-3 go-back-btn-large" onclick="prevSlide()">Go Back</button> --}}
                        </div>
                    </div>
                    {{-- END: SLIDE 1 --}}

                    {{-- SLIDE 2: Staff Selection --}}
                    <div class="carousel-item" id="staff-selection-slide">
                        <div class="question-slide">
                            <h3 style="padding: 50px; color: #0056b3;">Attended by / Inasikaso ni:</h3>
                            <div class="staff-scroll-container">
                                <div class="staff-scroll-panel">
                                    @foreach($staffs as $staff)
                                        <div class="text-center staff-item" data-division-id="{{ $staff->division_id }}">
                                            <label>
                                                <input type="radio" name="user_id" value="{{ $staff->id }}" id="staff-{{ $staff->id }}" data-division-id="{{ $staff->division_id }}" style="display:none;" required>
                                                <picture>
                                                    @if ($staff->getAvatarWebpUrl())
                                                        <source srcset="{{ $staff->getAvatarWebpUrl() }}" type="image/webp">
                                                    @endif
                                                    <img src="{{ $staff->getAvatarUrl() }}"
                                                        alt="{{ $staff->name }}'s profile picture"
                                                        class="staff-avatar"
                                                        loading="lazy">
                                                </picture>
                                            </label>
                                            <span class="staff-name">{{ $staff->name }}</span>
                                            <span class="staff-nickname">"{{ $staff->nickname }}"</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary mt-3 go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>
                    {{-- END: SLIDE 2 --}}

                    {{-- SLIDE 3: Service Selection (UPDATED GRID PAGINATION) --}}
                    <div class="carousel-item service-slide">
                        <div class="question-slide">
                            <h3 style="padding-bottom: 20px; color: crimson;">Service Received / Serbisyong Natanggap:</h3>

                            <div class="service-carousel-wrapper">
                                <button type="button" class="service-nav-btn prev-service-btn" onclick="changeServicePage(-1)">
                                    <i data-lucide="chevron-left"></i>
                                </button>

                                <div class="service-container" style="width: 100%;">
                                    <div id="service-grid-paginated">
                                        @foreach($services as $index => $service)
                                            @php $color = $colors[$index % count($colors)]; @endphp

                                            <div class="service-item text-center"
                                                data-division-id="{{ $service->division_id }}"
                                                data-service-id="{{ $service->id }}"
                                                style="display:none;"> <label>
                                                    <input type="radio" name="issue_id" value="{{ $service->id }}" style="display:none;" required>
                                                    <div class="service-icon-box" style="background-color: {{ $color }};">
                                                        <i data-lucide="{{ Str::after($service->icon, 'lucide-') }}" class="w-14 h-14"></i>
                                                    </div>
                                                    <span class="service-name">{{ $service->issue_description }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <button type="button" class="service-nav-btn next-service-btn" onclick="changeServicePage(1)">
                                    <i data-lucide="chevron-right"></i>
                                </button>
                            </div>

                            <div id="service-page-indicator" class="text-center mt-2 text-muted fw-bold"></div>

                            <button type="button" class="btn btn-primary mt-3 go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>
                    {{-- END: SLIDE 3 --}}

                    {{-- SLIDE 4: TIMELINESS (Rating 1) --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h2 style="color: orangered;">BILIS NG SERBISYO (TIMELINESS)</h2>
                            <p style="font-size: 27px;">Ang oras ng pagproseso ng inyong application/request ay akma o mas mabilis kaysa sa inaasahan.</p>
                            <p style="font-size: 27px;">(The time taken to process my application/request was reasonable or faster than expected.)</p>
                            <br>
                            <div class="d-flex flex-wrap justify-content-center">
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" style="display:none;" required>
                                    <span class="emoji-icon">😞</span>
                                    <span class="emoji-rating-text">Very Dissatisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" style="display:none;">
                                    <span class="emoji-icon">🙁</span>
                                    <span class="emoji-rating-text">Dissatisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" style="display:none;">
                                    <span class="emoji-icon">😊</span>
                                    <span class="emoji-rating-text">Satisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" style="display:none;">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Very Satisfied</span>
                                </label>
                            </div>
                            <button type="button" class="btn btn-primary mt-4 go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>
                    {{-- END: SLIDE 4 --}}

                    {{-- SLIDE 5: CLIENT HANDLING (Rating 2) --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h2 style="color: mediumvioletred;">PAKIKITUNGO SA KLIYENTE (CLIENT HANDLING)</h2>
                            <p>Magalang at propesyonal ang pakikitungo ng kawani na nagbigay ng serbisyo.</p>
                            <p>(The staff member was courteous and maintained a professional demeanor throughout the transaction.)</p>
                            <br>
                            <div class="d-flex flex-wrap justify-content-center">
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'handling_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="handling_rating" value="Very Dissatisfied" style="display:none;" required>
                                    <span class="emoji-icon">😞</span>
                                    <span class="emoji-rating-text">Very Dissatisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'handling_rating', 'Dissatisfied')">
                                    <input type="radio" name="handling_rating" value="Dissatisfied" style="display:none;">
                                    <span class="emoji-icon">🙁</span>
                                    <span class="emoji-rating-text">Dissatisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'handling_rating', 'Satisfied')">
                                    <input type="radio" name="handling_rating" value="Satisfied" style="display:none;">
                                    <span class="emoji-icon">😊</span>
                                    <span class="emoji-rating-text">Satisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'handling_rating', 'Very Satisfied')">
                                    <input type="radio" name="handling_rating" value="Very Satisfied" style="display:none;">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Very Satisfied</span>
                                </label>
                            </div>
                            <button type="button" class="btn btn-primary mt-4 go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>
                    {{-- END: SLIDE 5 --}}

                    {{-- SLIDE 6: QUALITY OF SERVICE --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h2 style="color: rgb(0, 182, 91);">KALIDAD NG SERBISYO (QUALITY OF SERVICE)</h2>
                            <p>Ang serbisyo o dokumentong natanggap ko ay tumpak, kompleto, at walang kamalian.</p>
                            <p>(The service or document I received was accurate, complete, and free of errors.)</p>
                            <br>
                            <div class="d-flex flex-wrap justify-content-center">
                                <!-- CHANGE: Set the 4th argument to FALSE here -->
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'quality_rating', 'Very Dissatisfied', false)">
                                    <input type="radio" name="quality_rating" value="Very Dissatisfied" style="display:none;" required>
                                    <span class="emoji-icon">😞</span>
                                    <span class="emoji-rating-text">Very Dissatisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'quality_rating', 'Dissatisfied', false)">
                                    <input type="radio" name="quality_rating" value="Dissatisfied" style="display:none;">
                                    <span class="emoji-icon">🙁</span>
                                    <span class="emoji-rating-text">Dissatisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'quality_rating', 'Satisfied', false)">
                                    <input type="radio" name="quality_rating" value="Satisfied" style="display:none;">
                                    <span class="emoji-icon">😊</span>
                                    <span class="emoji-rating-text">Satisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'quality_rating', 'Very Satisfied', false)">
                                    <input type="radio" name="quality_rating" value="Very Satisfied" style="display:none;">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Very Satisfied</span>
                                </label>
                            </div>
                            <button type="button" class="btn btn-primary mt-4 go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- SLIDE 7: OVERALL SATISFACTION --}}
                    <div class="carousel-item">
                        <div class="question-slide">
                            <h2 style="color: rgb(0, 182, 91);">OVERALL SATISFACTION</h2>
                            <p>Lubos akong nasiyahan sa serbisyong aking natanggap.</p>
                            <p>(I am satified with the serivce that I received)</p>
                            <br>
                            <div class="d-flex flex-wrap justify-content-center">
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'overall_rating', 'Very Dissatisfied', true)">
                                    <input type="radio" name="overall_rating" value="Very Dissatisfied" style="display:none;" required>
                                    <span class="emoji-icon">😞</span>
                                    <span class="emoji-rating-text">Very Dissatisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'overall_rating', 'Dissatisfied', true)">
                                    <input type="radio" name="overall_rating" value="Dissatisfied" style="display:none;">
                                    <span class="emoji-icon">🙁</span>
                                    <span class="emoji-rating-text">Dissatisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'overall_rating', 'Satisfied', true)">
                                    <input type="radio" name="overall_rating" value="Satisfied" style="display:none;">
                                    <span class="emoji-icon">😊</span>
                                    <span class="emoji-rating-text">Satisfied</span>
                                </label>
                                <label class="rating-option m-2 d-flex flex-column align-items-center" onclick="selectRating(this, 'overall_rating', 'Very Satisfied', true)">
                                    <input type="radio" name="overall_rating" value="Very Satisfied" style="display:none;">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Very Satisfied</span>
                                </label>
                            </div>
                            <button type="button" class="btn btn-primary mt-4 go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- SLIDE 8: QR Code / Thank You Page --}}
                    <div class="carousel-item" id="qr-timeout-slide">
                        <div class="question-slide">

                            @if(session('success'))
                                <div id="success-message" class="alert alert-success" role="alert"
                                    style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-top: 20px;">
                                    {{ session('success') }}
                                </div>
                            @endif

                            <h3 style="padding: 50px; color: #0056b3;">Scan the QR code to fill out the comments and suggestions form</h3>

                            <div class="qr-code-container d-flex justify-content-center">
                                {!! QrCode::size(350)->backgroundColor(255, 255, 255, 0)->generate('https://forms.gle/Tvmm2WmjHGNqteUD9') !!}
                            </div>

                            <br>

                            <div class="mt-3">
                                <button type="button" class="btn btn-primary m-3 go-back-btn-large" onclick="window.location.reload()">Rate Again</button>
                            </div>
                        </div>
                    </div>
                    {{-- END: SLIDE 8 --}}

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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
        const surveyCarouselEl = document.getElementById('surveyCarousel');
        const surveyCarousel = new bootstrap.Carousel(surveyCarouselEl, {
            touch: false,
            interval: false
        });

        // --- GLOBAL VARIABLES FOR SERVICE PAGINATION ---
        let servicePage = 0;
        const itemsPerPage = 8; // 4 columns * 2 rows
        let $currentServiceSet = $(); // Holds the filtered list of services

        function nextSlide() {
            surveyCarousel.next();
        }

        function prevSlide() {
            surveyCarousel.prev();
            $('.rating-option').removeClass('active');
        }

        function submitForm() {
            const form = document.querySelector('form');
            form.submit();
        }

        // --- SERVICE GRID PAGINATION FUNCTIONS ---
        function renderServiceGrid() {
            // 1. Hide ALL items initially
            $('.service-item').hide();

            // 2. Calculate range for current page
            const start = servicePage * itemsPerPage;
            const end = start + itemsPerPage;

            // 3. Show only items in the slice
            const $visibleSlice = $currentServiceSet.slice(start, end);
            $visibleSlice.fadeIn(200);

            // 4. Update Button States
            const totalPages = Math.ceil($currentServiceSet.length / itemsPerPage);

            // Disable Prev if on page 0
            $('.prev-service-btn').prop('disabled', servicePage === 0)
                                .toggleClass('disabled-visual', servicePage === 0);

            // Disable Next if on last page (or if there are no items)
            const isLastPage = (servicePage + 1) >= totalPages || totalPages === 0;
            $('.next-service-btn').prop('disabled', isLastPage)
                                .toggleClass('disabled-visual', isLastPage);

            // 5. Update Text Indicator
            if ($currentServiceSet.length > 0) {
                $('#service-page-indicator').text(`Page ${servicePage + 1} of ${totalPages}`);
            } else {
                $('#service-page-indicator').text('');
            }
        }

        function changeServicePage(direction) {
            servicePage += direction;
            renderServiceGrid();
        }

        function selectRating(selectedLabel, inputName, value, isLastQuestion = false) {
            var container = selectedLabel.closest('.d-flex');
            var labels = container.querySelectorAll('label.rating-option');

            labels.forEach(function(label) {
                label.classList.remove('active');
            });

            selectedLabel.classList.add('active');

            var input = selectedLabel.querySelector('input[type="radio"]');
            if (input) {
                input.checked = true;
            }

            nextSlide();

            if (isLastQuestion) {
                setTimeout(function() {
                    submitForm();
                }, 400);
            }
        }

        function reActivateRating(slideElement) {
            const $slide = $(slideElement);
            const $checkedInput = $slide.find('input[type="radio"]:checked');

            $slide.find('label.rating-option').removeClass('active');

            if ($checkedInput.length) {
                $checkedInput.closest('label.rating-option').addClass('active');
            }
        }

        $(document).ready(function() {
            // Initialize Icons
            lucide.createIcons();

            // --- HEADER TOGGLE FUNCTION ---
            function toggleHeader(slideIndex) {
                if (slideIndex === 0) {
                    $('.center-logo-container, h5, h1').show();
                } else {
                    $('.center-logo-container, h5, h1').hide();
                }
            }
            toggleHeader(0);

            // --- DIVISION SELECTION (Slide 1) ---
            $('.division-btn').on('click', function() {
                var selecteddivisionId = $(this).data('division-id');

                $('.division-btn').removeClass('active');
                $(this).addClass('active');

                if (selecteddivisionId === 'all') {
                    $('.staff-item').show();
                } else {
                    $('.staff-item').hide();
                    $('.staff-item[data-division-id="' + selecteddivisionId + '"]').show();
                }

                $('#staff-selection-slide').data('current-division-id', selecteddivisionId);

                setTimeout(() => {
                    surveyCarousel.next();
                }, 300);
            });

            // --- STAFF SELECTION (Slide 2) ---
            $('.staff-item label').on('click', function(e) {
                e.preventDefault();

                var $input = $(this).find('input[name="user_id"]');
                var $avatar = $(this).find('.staff-avatar');

                $('input[name="user_id"]').prop('checked', false);
                $('.staff-avatar').removeClass('selected');

                $input.prop('checked', true);
                $avatar.addClass('selected');

                $input.trigger('change');

                setTimeout(() => {
                    surveyCarousel.next();
                }, 300);
            });

            // --- SERVICE FILTERING & PAGINATION TRIGGER (Slide 3) ---
            $('input[name="user_id"]').on('change', function() {
                var selecteddivisionId = $(this).data('division-id').toString();

                // 1. Filter: Find all items matching this division and store them
                $currentServiceSet = $('.service-item[data-division-id="' + selecteddivisionId + '"]');

                // 2. Clear old selections
                $('input[name="issue_id"]').prop('checked', false);
                $('.service-item').removeClass('selected');

                // 3. Reset to Page 0
                servicePage = 0;

                // 4. Render the grid
                renderServiceGrid();
            });

            // --- SERVICE SELECTION VISUALS ---
            $('input[name="issue_id"]').on('change', function () {
                $('.service-item').removeClass('selected');
                $(this).closest('.service-item').addClass('selected');
                setTimeout(() => surveyCarousel.next(), 300);
            });

            // --- CAROUSEL SLIDE CONTROLLER ---
            $('#surveyCarousel').on('slid.bs.carousel', function (e) {
                const currentSlideIndex = e.to;
                const $relatedTarget = $(e.relatedTarget);

                toggleHeader(currentSlideIndex);

                // Ensure Grid is rendered if we land on Service Slide
                if ($relatedTarget.hasClass('service-slide')) {
                    if ($('input[name="user_id"]:checked').length > 0) {
                        renderServiceGrid();
                    }
                }

                reActivateRating(e.relatedTarget);

                const indicators = document.querySelectorAll('.carousel-indicators button');
                indicators.forEach((indicator, index) => {
                    indicator.classList.remove('active');
                    indicator.removeAttribute('aria-current');
                    if (index === currentSlideIndex) {
                        indicator.classList.add('active');
                        indicator.setAttribute('aria-current', 'true');
                    }
                });
            });

            // Auto-hide success message
            setTimeout(() => {
                $('#success-message').fadeOut('slow');
            }, 5000);
        });

        // --- QR REDIRECT LOGIC ---
        document.addEventListener('DOMContentLoaded', function() {
            const QR_SLIDE_INDEX = 7;
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.has('thank_you') && surveyCarouselEl) {
                history.replaceState({}, document.title, window.location.pathname);
                surveyCarousel.to(QR_SLIDE_INDEX);
            }
        });

        // --- TIMEOUT LOGIC ---
        document.addEventListener('DOMContentLoaded', function() {
            const qrSlide = document.getElementById('qr-timeout-slide');
            let timeoutId;

            if (surveyCarouselEl && qrSlide) {
                surveyCarouselEl.addEventListener('slid.bs.carousel', function () {
                    if (qrSlide.classList.contains('active')) {
                        clearTimeout(timeoutId);
                        const timeoutDuration = 3 * 60 * 1000; // 3 minutes
                        console.log('QR slide active. Setting timeout for 3 minutes.');

                        timeoutId = setTimeout(function() {
                            window.location.reload();
                        }, timeoutDuration);
                    } else {
                        clearTimeout(timeoutId);
                    }
                });
            }
        });
    </script>
</body>
</html>
