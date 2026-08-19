@php
    $minimumPrice = $court->prices->min('price') ?? 0;
    $galleryImages = $court->images->take(5);
    $coverImage = $galleryImages->first()?->image;
    $rating = $rating_stats->average ? number_format($rating_stats->average, 1) : null;
    $reviewsCount = $rating_stats->total ?? 0;
    $isFavorite = auth()->check() && auth()->user()->favorites()->where('court_id', $court->id)->exists();
@endphp
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $court->name }} – SmashZone</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #082635;
            --navy-2: #0b2f45;
            --green: #05d381;
            --green-dark: #009e5b;
            --paper: #fff;
            --bg: #f2f6f4;
            --line: #dce8e2;
            --muted: #71818a;
            --ink: #111827
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            -webkit-font-smoothing: antialiased
        }

        a {
            text-decoration: none;
            color: inherit
        }

        .shell {
            width: min(1180px, calc(100% - 48px));
            margin: auto
        }

        .header {
            background: var(--navy);
            color: #fff
        }

        .nav {
            height: 84px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -1px
        }

        .brand img {
            height: 42px;
            border-radius: 10px
        }

        .menu {
            display: flex;
            gap: 30px
        }

        .menu a,
        .user {
            color: #e8f1ee;
            font-size: 14px;
            font-weight: 700
        }

        .menu a:hover,
        .menu .active {
            color: var(--green)
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 16px
        }

        .user {
            white-space: nowrap
        }

        .cta {
            border: 0;
            border-radius: 11px;
            background: var(--green);
            color: #062b27;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap
        }

        .cta:hover {
            background: #4ff49f;
            color: #061c13
        }

        .hero {
            padding: 34px 0 46px;
            background: radial-gradient(circle at 80% 10%, rgba(5, 211, 129, .14), transparent 32%), var(--navy)
        }

        .crumb {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 20px;
            color: #9db4ae;
            font-size: 13px;
            font-weight: 700
        }

        .crumb a {
            color: #c6d4d0
        }

        .crumb a:hover {
            color: var(--green)
        }

        .court-hero {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 30px;
            align-items: start
        }

        .gallery {
            position: relative;
            overflow: hidden;
            border-radius: 18px;
            background: #0e2c3a
        }

        .gallery-main {
            position: relative;
            height: 430px;
            overflow: hidden;
            background: #0a2230
        }

        .gallery-main img {
            width: 100%;
            height: 100%;
            object-fit: cover
        }

        .gallery-fallback {
            display: grid;
            place-items: center;
            height: 100%;
            color: #fff;
            font-size: 72px;
            background: linear-gradient(135deg, #0b4b4d, #2acb78)
        }

        .gallery-type {
            position: absolute;
            left: 16px;
            bottom: 16px;
            padding: 8px 13px;
            border-radius: 100px;
            background: rgba(6, 32, 44, .85);
            color: #eaffee;
            font-size: 12px;
            font-weight: 700
        }

        .gallery-thumbs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 14px
        }

        .gallery-thumb {
            flex: 0 0 92px;
            height: 66px;
            padding: 0;
            border: 2px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            background: transparent;
            opacity: .62;
            cursor: pointer;
            transition: .2s
        }

        .gallery-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover
        }

        .gallery-thumb.active,
        .gallery-thumb:hover {
            border-color: #34e699;
            opacity: 1
        }

        .info-card {
            position: sticky;
            top: 20px;
            padding: 28px;
            border-radius: 18px;
            background: var(--paper);
            box-shadow: 0 18px 45px rgba(8, 38, 53, .16)
        }

        .info-kicker {
            display: inline-block;
            margin-bottom: 8px;
            color: var(--green-dark);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase
        }

        .info-card h1 {
            margin: 0 0 12px;
            color: var(--ink);
            font-size: 30px;
            letter-spacing: -1px;
            line-height: 1.2;
            font-weight: 800
        }

        .rating-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px
        }

        .rating-row .stars {
            color: #f6b725;
            font-size: 14px
        }

        .rating-row strong {
            color: var(--ink);
            font-size: 15px
        }

        .rating-row small {
            color: #849199;
            font-size: 12px;
            font-weight: 600
        }

        .contact-list {
            display: flex;
            flex-direction: column;
            gap: 11px;
            padding: 15px;
            border-radius: 12px;
            background: #f4f8f6
        }

        .contact-list p {
            display: flex;
            gap: 10px;
            margin: 0;
            color: #536771;
            font-size: 13px;
            line-height: 1.55
        }

        .contact-list i {
            margin-top: 2px;
            color: var(--green-dark);
            font-size: 14px
        }

        .contact-list strong {
            color: #203a4b
        }

        .contact-list span {
            flex: 1
        }

        .price-line {
            display: flex;
            align-items: end;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #edf1ef
        }

        .price-line small {
            display: block;
            color: #89969c;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase
        }

        .price-line strong {
            color: var(--green-dark);
            font-size: 27px;
            letter-spacing: -1px
        }

        .price-line em {
            color: #89969c;
            font-size: 12px;
            font-style: normal;
            font-weight: 600
        }

        .info-actions {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            margin-top: 20px
        }

        .btn-book {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 12px;
            background: var(--green);
            color: #05311e;
            padding: 14px 20px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer
        }

        .btn-book:hover {
            background: #00b967;
            color: #fff
        }

        .btn-fav {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            border: 1px solid #f0c0c0;
            border-radius: 12px;
            background: #fff;
            color: #e05252;
            font-size: 18px;
            cursor: pointer
        }

        .btn-fav:hover {
            background: #feeceb
        }

        .section {
            padding: 56px 0
        }

        .section-alt {
            background: #fff
        }

        .section-head {
            margin-bottom: 30px
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 7px;
            color: var(--green-dark);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase
        }

        .section-head h2 {
            margin: 0;
            color: var(--ink);
            font-size: 30px;
            letter-spacing: -1.2px;
            font-weight: 800
        }

        .section-head p {
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.65
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px
        }

        .amenity-card {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 13px;
            background: #fff
        }

        .amenity-card i {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: #eafaf1;
            color: var(--green-dark);
            font-size: 17px
        }

        .amenity-card span {
            color: #35515e;
            font-size: 13px;
            font-weight: 700
        }

        .schedule-box {
            padding: 26px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 12px 35px rgba(8, 38, 53, .07)
        }

        .date-strip {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 6px;
            margin-bottom: 24px
        }

        .date-card {
            flex: 0 0 82px;
            height: 98px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 3px;
            border: 1px solid #d4ded3;
            border-radius: 14px;
            color: #112338;
            background: #fff;
            transition: .18s
        }

        .date-card:hover {
            border-color: var(--green)
        }

        .date-card.is-selected {
            color: #fff;
            background: var(--green-dark);
            border-color: var(--green-dark);
            box-shadow: 0 6px 16px rgba(0, 158, 91, .25)
        }

        .date-card small {
            color: #55708c;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase
        }

        .date-card.is-selected small {
            color: #eaffee
        }

        .date-card strong {
            font-size: 24px;
            line-height: 1
        }

        .date-card em {
            font-style: normal;
            color: #8d9cac;
            font-size: 11px
        }

        .date-card.is-selected em {
            color: #eaffee
        }

        .schedule-legend {
            display: flex;
            gap: 24px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 18px;
            color: #58708b;
            font-size: 13px;
            font-weight: 600
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 7px
        }

        .legend-dot {
            width: 16px;
            height: 16px;
            border-radius: 5px
        }

        .legend-dot.available {
            background: #eff9ee;
            border: 1px solid #bfe3c6
        }

        .legend-dot.selected {
            background: #f5b910
        }

        .legend-dot.booked {
            background: #ef6464
        }

        .legend-dot.locked {
            background: #e5e9e6
        }

        .slot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
            gap: 10px
        }

        .slot {
            border: 1px solid #cfe7d6;
            border-radius: 10px;
            padding: 15px 8px;
            text-align: center;
            background: #eff9ee;
            color: var(--green-dark);
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            transition: .15s
        }

        .slot:hover:not(:disabled) {
            background: #d8f5e2
        }

        .slot.is-selected {
            color: #fff;
            background: #f5b910;
            border-color: #f5b910
        }

        .slot.is-booked,
        .slot.is-locked {
            color: #a8b2ac;
            background: #eef1ef;
            border-color: #dfe5e1;
            cursor: not-allowed
        }

        .slot.is-booked {
            color: #fff;
            background: #ef6464;
            border-color: #ef6464
        }

        .slot small {
            display: block;
            margin-top: 3px;
            font-size: 11px;
            font-weight: 700
        }

        .selection-bar {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 30;
            display: none;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            border-radius: 14px;
            color: #fff;
            background: #102030;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .28)
        }

        .selection-bar.visible {
            display: flex
        }

        .selection-bar .btn-book {
            padding: 10px 16px;
            border-radius: 9px;
            text-decoration: none
        }

        .reviews-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 28px
        }

        .rating-summary {
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff
        }

        .rating-big {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px
        }

        .rating-big strong {
            font-size: 48px;
            letter-spacing: -2px;
            color: var(--ink)
        }

        .rating-big .stars {
            color: #f6b725;
            font-size: 16px
        }

        .rating-big small {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-top: 3px
        }

        .rating-bars {
            display: flex;
            flex-direction: column;
            gap: 8px
        }

        .rating-bar-row {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 12px;
            color: #55708c
        }

        .rating-bar-row span {
            width: 14px;
            text-align: right;
            font-weight: 700
        }

        .rating-bar {
            flex: 1;
            height: 7px;
            border-radius: 99px;
            background: #edf1ef;
            overflow: hidden
        }

        .rating-bar i {
            display: block;
            height: 100%;
            border-radius: 99px;
            background: #f5b910
        }

        .review-list {
            display: flex;
            flex-direction: column;
            gap: 14px
        }

        .review-item {
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff
        }

        .review-head {
            display: flex;
            align-items: center;
            gap: 10px
        }

        .review-avatar {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #dcfce7;
            color: #15803d;
            font-weight: 800
        }

        .review-head strong,
        .review-head small {
            display: block;
            font-size: 13px
        }

        .review-head small {
            color: var(--muted);
            margin-top: 2px
        }

        .review-head .stars {
            margin-left: auto;
            color: #f6b725;
            font-size: 12px
        }

        .review-content {
            margin: 13px 0 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.7
        }

        .footer {
            padding: 52px 0 23px;
            background: var(--navy);
            color: #c1d0cb
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 45px
        }

        .footer h4 {
            margin: 4px 0 14px;
            color: #fff;
            font-size: 14px
        }

        .footer a {
            display: block;
            margin: 9px 0;
            color: #bdcec8;
            font-size: 13px
        }

        .footer .brand {
            display: inline-flex;
            font-size: 20px
        }

        .copy {
            margin: 12px 0 0;
            max-width: 310px;
            font-size: 13px;
            line-height: 1.7
        }

        .bottom {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #234451;
            font-size: 12px
        }

        @media(max-width:991px) {
            .menu {
                display: none
            }

            .nav {
                height: 78px
            }

            .court-hero {
                grid-template-columns: 1fr
            }

            .info-card {
                position: static
            }

            .gallery-main {
                height: 300px
            }

            .reviews-layout {
                grid-template-columns: 1fr
            }

            .amenities-grid {
                grid-template-columns: repeat(2, 1fr)
            }
        }

        @media(max-width:620px) {
            .shell {
                width: min(1180px, calc(100% - 28px))
            }

            .user {
                display: none
            }

            .cta {
                padding: 11px 14px
            }

            .brand {
                font-size: 20px
            }

            .brand img {
                height: 36px
            }

            .gallery-main {
                height: 230px
            }

            .info-card {
                padding: 20px
            }

            .info-card h1 {
                font-size: 24px
            }

            .amenities-grid {
                grid-template-columns: 1fr
            }

            .info-actions {
                grid-template-columns: 1fr
            }

            .btn-fav {
                width: 100%
            }

            .slot-grid {
                grid-template-columns: repeat(auto-fill, minmax(76px, 1fr))
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px
            }

            .footer-grid>div:first-child {
                grid-column: span 2
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <nav class="shell nav">
            <a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/logo.png') }}" alt="SmashZone"></a>
            <div class="menu"><a href="{{ route('home') }}">Trang chủ</a><a class="active"
                    href="{{ route('courts.index') }}">Sân cầu lông</a><a href="{{ route('home') }}#offers">Khuyến
                    mãi</a><a href="{{ route('home') }}#news">Tin tức</a><a href="{{ route('home') }}#why">Giới
                    thiệu</a></div>
            <div class="actions">@auth<a class="user"
            href="{{ route('bookings.index') }}">{{ Str::limit(Auth::user()->name, 16) }}</a>@else<a class="user"
                    href="{{ route('login') }}">Đăng nhập</a>@endauth<a class="cta" href="#booking">Đặt sân ngay</a>
            </div>
        </nav>
        <section class="hero">
            <div class="shell">
                <div class="crumb"><a href="{{ route('home') }}">Trang chủ</a><i class="bi bi-chevron-right"></i><a
                        href="{{ route('courts.index') }}">Sân cầu lông</a><i
                        class="bi bi-chevron-right"></i><span>{{ $court->name }}</span></div>
                <div class="court-hero">
                    <div class="gallery">
                        <div class="gallery-main">
                            @if($coverImage)<img id="mainImage" src="{{ $coverImage }}" alt="{{ $court->name }}">@else
                            <div class="gallery-fallback"><i class="bi bi-trophy-fill"></i></div>@endif
                            @if($court->courtType)<span class="gallery-type">{{ $court->courtType->name }}</span>@endif
                        </div>
                        @if($galleryImages->count() > 1)
                            <div class="gallery-thumbs">@foreach($galleryImages as $image)<button type="button"
                                class="gallery-thumb @if($loop->first) active @endif"
                                onclick="switchImage('{{ $image->image }}', this)"><img src="{{ $image->image }}"
                        alt="Ảnh {{ $loop->iteration }}"></button>@endforeach</div>@endif
                    </div>
                    <aside class="info-card">
                        @if($court->courtType)<span class="info-kicker">{{ $court->courtType->name }}</span>@endif
                        <h1>{{ $court->name }}</h1>
                        <div class="rating-row">@if($rating)<span class="stars">@for($i = 1; $i <= 5; $i++)<i
                            class="bi bi-star{{ $i <= round($rating) ? '-fill' : '' }}"></i>@endfor</span><strong>{{ $rating }}</strong><small>({{ $reviewsCount }}
                        đánh giá)</small>@else<span class="stars"><i class="bi bi-stars"></i></span><small>Sân
                                chất lượng</small>@endif</div>
                        <div class="contact-list">
                            <p><i class="bi bi-geo-alt-fill"></i><span><strong>Địa chỉ:</strong>
                                    {{ $court->address ?? 'Đang cập nhật' }}</span></p>
                            @if($court->phone)
                                <p><i class="bi bi-telephone-fill"></i><span><strong>Hotline:</strong>
                            {{ $court->phone }}</span></p>@endif
                            @if($court->opening_time && $court->closing_time)
                                <p><i class="bi bi-clock-fill"></i><span><strong>Giờ mở cửa:</strong> Thứ 2 - CN:
                                        {{ \Carbon\Carbon::parse($court->opening_time)->format('H:i') }} -
                            {{ \Carbon\Carbon::parse($court->closing_time)->format('H:i') }}</span></p>@endif
                        </div>
                        <div class="price-line">
                            <div><small>Giá thuê
                                    từ</small><strong>{{ number_format($minimumPrice, 0, ',', '.') }}đ</strong>
                                <em>/giờ</em></div>
                        </div>
                        <div class="info-actions">
                            <a class="btn-book" href="#booking">Đặt sân ngay <i class="bi bi-arrow-right"></i> </a>
                            @auth
                                @if($isFavorite)
                                    <form method="POST" action="{{ route('favorites.destroy', $court) }}">@csrf
                                        @method('DELETE')<button type="submit" class="btn-fav" title="Bỏ yêu thích"><i
                                                class="bi bi-heart-fill"></i></button></form>
                                @else
                                    <form method="POST" action="{{ route('favorites.store', $court) }}">@csrf<button
                                            type="submit" class="btn-fav" title="Thêm vào yêu thích"><i
                                                class="bi bi-heart"></i></button></form>
                                @endif
                            @else
                                <a class="btn-fav" href="{{ route('login') }}" title="Đăng nhập để yêu thích"><i
                                        class="bi bi-heart"></i></a>
                            @endauth
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </header>

    <main>
        @if($court->description)
            <section class="section">
                <div class="shell">
                    <div class="section-head"><span class="eyebrow">Giới thiệu</span>
                        <h2>Về sân bóng</h2>
                    </div>
                    <p style="max-width:760px;margin:0;color:var(--muted);font-size:15px;line-height:1.8">
                        {{ $court->description }}</p>
                </div>
        </section>@endif

        @if($court->amenities->isNotEmpty())
            <section class="section section-alt">
                <div class="shell">
                    <div class="section-head"><span class="eyebrow">Tiện ích</span>
                        <h2>Trang thiết bị & Dịch vụ</h2>
                    </div>
                    <div class="amenities-grid">@foreach($court->amenities as $amenity)
                        <div class="amenity-card"><i class="bi bi-check-circle-fill"></i><span>{{ $amenity->name }}</span>
                    </div>@endforeach
                    </div>
                </div>
        </section>@endif

        <section class="section" id="booking">
            <div class="shell">
                <div class="section-head"><span class="eyebrow">Đặt sân</span>
                    <h2>Chọn ngày & khung giờ</h2>
                    <p>Chọn các khung giờ còn trống để đặt sân nhanh chóng.</p>
                </div>
                <div class="schedule-box">
                    <div class="date-strip">@foreach($dateRange as $date)<a
                        href="{{ route('courts.show', $court) }}?booking_date={{ $date->toDateString() }}"
                    class="date-card {{ $date->isSameDay($selectedDate) ? 'is-selected' : '' }}"><small>{{ ['CN', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'][$date->dayOfWeek] }}</small><strong>{{ $date->day }}</strong><em>T{{ $date->month }}</em></a>@endforeach
                    </div>
                    <div class="schedule-legend"><span class="legend-item"><i class="legend-dot selected"></i>Đã
                            chọn</span><span class="legend-item"><i class="legend-dot available"></i>Trống</span><span
                            class="legend-item"><i class="legend-dot booked"></i>Đã đặt</span><span
                            class="legend-item"><i class="legend-dot locked"></i>Không khả dụng</span></div>
                    @if($errors->any() || session('error'))
                        <div
                            style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#feeceb;color:#b32e27;font-size:13px">
                    {{ session('error') ?: $errors->first() }}</div>@endif
                    <form method="POST" action="{{ route('bookings.store') }}" id="bookingForm">@csrf<input
                            type="hidden" name="court_id" value="{{ $court->id }}"><input type="hidden"
                            name="booking_date" value="{{ $selectedDate->toDateString() }}">
                        <div id="selectedSlots"></div>
                        <div class="slot-grid">
                            @foreach($timeSlots as $slot)@php($item = $availability[$slot->id] ?? ['status' => 'MAINTENANCE', 'price' => 0])@php($status = $item['status'])@php($class = match ($status) { 'BOOKED' => 'is-booked', 'MAINTENANCE' => 'is-locked', default => ($item['price'] > 0 ? '' : 'is-locked')})<button
                                type="button" class="slot {{ $class }}" data-slot="{{ $slot->id }}"
                                data-price="{{ $item['price'] }}" {{ $status !== 'AVAILABLE' || $item['price'] <= 0 ? 'disabled' : '' }}>{{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}<small>{{ $item['price'] > 0 ? number_format($item['price'] / 1000, 0) . 'k' : '—' }}</small></button>@endforeach
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="section section-alt">
            <div class="shell">
                <div class="section-head"><span class="eyebrow">Đánh giá</span>
                    <h2>Khách hàng nói gì</h2>
                    <p>Cảm nhận thực tế từ cộng đồng người chơi.</p>
                </div>
                <div class="reviews-layout">
                    <aside class="rating-summary">
                        <div class="rating-big"><strong>{{ $rating ?: '—' }}</strong>
                            <div>@if($rating)
                                <div class="stars">@for($i = 1; $i <= 5; $i++)<i
                                class="bi bi-star{{ $i <= round($rating) ? '-fill' : '' }}"></i>@endfor</div>
                            <small>{{ $reviewsCount }} đánh giá</small>@else<div class="stars"><i
                                class="bi bi-stars"></i></div><small>Chưa có đánh giá</small>@endif
                            </div>
                        </div>
                        <div class="rating-bars">
                            @foreach([5, 4, 3, 2, 1] as $star)@php($countKey = 'count_' . $star)@php($count = $rating_stats->{$countKey} ?? 0)@php($total = max($reviewsCount, 1))
                            <div class="rating-bar-row"><span>{{ $star }}</span><i class="bi bi-star-fill"
                                    style="color:#f6b725;font-size:10px"></i>
                                <div class="rating-bar"><i style="width:{{ ($count / $total) * 100 }}%"></i></div><span
                                    style="width:auto">{{ $count }}</span>
                            </div>@endforeach
                        </div>
                    </aside>
                    <div class="review-list">@forelse($reviews as $review)
                        <article class="review-item">
                            <div class="review-head">
                                <div class="review-avatar">{{ Str::upper(Str::substr($review->user->name, 0, 1)) }}</div>
                                <div>
                                    <strong>{{ $review->user->name }}</strong><small>{{ $review->created_at->format('d/m/Y') }}</small>
                                </div><span class="stars">@for($i = 1; $i <= 5; $i++)<i
                                class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"></i>@endfor</span>
                            </div>
                            <p class="review-content">{{ $review->content }}</p>
                    </article>@empty<div class="review-item" style="text-align:center;color:var(--muted)">Chưa có
                        đánh giá nào cho sân này.</div>@endforelse
                    </div>
                </div>
                @if($reviews->hasPages())
                    <div style="display:flex;justify-content:center;gap:8px;margin-top:26px">{{ $reviews->links() }}</div>
                @endif
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="shell">
            <div class="footer-grid">
                <div><a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/logo.png') }}"
                            alt="SmashZone" style="height:40px;border-radius:10px;margin-right:8px"></a>
                    <p class="copy">Nền tảng đặt sân cầu lông đơn giản, nhanh chóng và tiện lợi.</p>
                </div>
                <div>
                    <h4>Khám phá</h4><a href="{{ route('home') }}">Trang chủ</a><a
                        href="{{ route('courts.index') }}">Sân cầu lông</a><a href="{{ route('home') }}#news">Tin
                        tức</a>
                </div>
                <div>
                    <h4>Liên hệ</h4><a href="mailto:hello@smashzone.vn">hello@smashzone.vn</a><a
                        href="tel:0982949974">0982 949 974</a><a href="#">Hà Nội, Việt Nam</a>
                </div>
            </div>
            <div class="bottom">© {{ now()->year }} SmashZone. All rights reserved.</div>
        </div>
    </footer>

    <div class="selection-bar" id="selectionBar"><span id="selectionSummary"></span><button type="submit"
            form="bookingForm" class="btn-book">@auth Tiếp tục @else Đăng nhập để đặt @endauth</button></div>

    <script>
        function switchImage(src, el) { const main = document.getElementById('mainImage'); if (main) main.src = src; document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active')); if (el) el.classList.add('active'); }
        const selected = new Map(), slotsContainer = document.getElementById('selectedSlots'), selectionBar = document.getElementById('selectionBar');
        document.querySelectorAll('.slot:not(:disabled)').forEach(btn => btn.addEventListener('click', () => {
            const id = btn.dataset.slot;
            if (selected.has(id)) { selected.delete(id); btn.classList.remove('is-selected'); } else { selected.set(id, Number(btn.dataset.price)); btn.classList.add('is-selected'); }
            slotsContainer.innerHTML = [...selected.keys()].map(id => `<input type="hidden" name="time_slot_ids[]" value="${id}">`).join('');
            const total = [...selected.values()].reduce((s, p) => s + p, 0);
            document.getElementById('selectionSummary').textContent = `${selected.size} khung giờ · ${total.toLocaleString('vi-VN')}đ`;
            selectionBar.classList.toggle('visible', selected.size > 0);
        }));
    </script>
</body>

</html>