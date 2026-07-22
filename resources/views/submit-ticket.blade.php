@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="submit-ticket-hero">
                <div class="submit-ticket-hero__inner">
                    <div class="submit-ticket-hero__icon">
                        <i class="la la-life-ring"></i>
                    </div>

                    <h1 class="submit-ticket-hero__title">Need help with something?</h1>
                    <p class="submit-ticket-hero__subtitle">
                        Submit a ticket and the right HR team will get back to you.
                    </p>

                    <a href="{{ $ticketCreateUrl }}" class="btn btn-lg submit-ticket-hero__cta">
                        <i class="la la-paper-plane"></i> Submit Ticket
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .submit-ticket-hero {
        min-height: 60vh;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 2rem;
        background: linear-gradient(180deg, #f9fbfd 0%, #f1f4f8 100%);
        border-radius: 8px;
    }

    .submit-ticket-hero__inner {
        max-width: 560px;
    }

    .submit-ticket-hero__icon {
        font-size: 3rem;
        color: #467fd0;
        margin-bottom: 1rem;
    }

    .submit-ticket-hero__title {
        font-size: 2.25rem;
        font-weight: 700;
        color: #1b2a4e;
        margin-bottom: 0.75rem;
    }

    .submit-ticket-hero__subtitle {
        font-size: 1.05rem;
        color: #869ab8;
        margin-bottom: 2rem;
    }

    .submit-ticket-hero__cta {
        background: #467fd0;
        border: none;
        color: #fff;
        padding: 0.85rem 2.25rem;
        font-size: 1.05rem;
        font-weight: 600;
        border-radius: 8px;
        box-shadow: 0 4px 14px rgba(70, 127, 208, 0.35);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .submit-ticket-hero__cta:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(70, 127, 208, 0.45);
    }

    @media (max-width: 576px) {
        .submit-ticket-hero {
            min-height: 50vh;
            padding: 1.25rem;
        }

        .submit-ticket-hero__title {
            font-size: 1.6rem;
        }

        .submit-ticket-hero__subtitle {
            font-size: 0.95rem;
        }

        .submit-ticket-hero__cta {
            width: 100%;
            padding: 0.85rem 1.5rem;
        }
    }
</style>
@endsection
