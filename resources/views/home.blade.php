<?php
    $currentRole = Auth::user()?->role;
    $primaryRoute = $currentRole === 'ADMIN'
        ? route('admin.dashboard')
        : ($currentRole === 'EMPLOYEE' ? route('employee.dashboard') : route('courts.index'));
    $primaryLabel = $currentRole === 'ADMIN'
        ? 'Vào quản trị'
        : ($currentRole === 'EMPLOYEE' ? 'Vào vận hành' : 'Đặt sân ngay');
    $canBook = ! Auth::check() || in_array($currentRole, ['CUSTOMER', null], true);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmashZone – Đặt sân cầu lông nhanh chóng</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #0ea36b;
            --brand-dark: #0b8a5a;
            --ink: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --surface: #ffffff;
            --bg: #f8fafc;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--ink);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; color: inherit; }

        .container-wide { width: min(1180px, calc(100% - 32px)); margin-inline: auto; }

        /* NAV */
        .nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 50;
            padding: 16px 0;
            transition: background .25s, box-shadow .25s, padding .25s;
        }
        .nav.scrolled {
            background: rgba(8, 34, 51, .94);
            backdrop-filter: blur(14px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, .18);
            padding: 10px 0;
        }
        .nav-inner { display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .brand { display: flex; align-items: center; gap: 10px; color: #fff; font-weight: 800; font-size: 21px; letter-spacing: -.5px; }
        .brand img { height: 44px; border-radius: 10px; }
        .nav-links { display: flex; gap: 28px; }
        .nav-links a { color: rgba(255, 255, 255, .88); font-size: 14px; font-weight: 600; transition: color .2s; }
        .nav-links a:hover { color: #5eead4; }
        .nav-actions { display: flex; align-items: center; gap: 12px; }
        .btn-pill {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 11px 20px; border-radius: 999px; font-weight: 700; font-size: 14px;
            border: 0; cursor: pointer; transition: all .2s; white-space: nowrap;
        }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); transform: translateY(-1px); }
        .btn-ghost { background: rgba(255, 255, 255, .12); color: #fff; }
        .btn-ghost:hover { background: rgba(255, 255, 255, .22); }
        .nav-user { color: rgba(255, 255, 255, .9); font-size: 14px; font-weight: 600; }

        /* Dropdown tài khoản hiển thị khi hover */
        .nav-user-menu { position: relative; }
        .nav-user-trigger {
            display: inline-flex; align-items: center; gap: 7px;
            color: rgba(255, 255, 255, .9); font-size: 14px; font-weight: 600;
            background: none; border: 0; cursor: pointer; padding: 4px 0;
        }
        .nav-user-trigger:hover { color: #5eead4; }
        .nav-user-trigger .caret { font-size: 11px; transition: transform .2s; }
        .nav-user-menu:hover .nav-user-trigger .caret { transform: rotate(180deg); }
        .nav-user-dropdown {
            position: absolute; top: 100%; right: 0; min-width: 220px;
            padding: 8px; background: #fff; border-radius: 12px;
            box-shadow: 0 16px 40px rgba(2, 24, 35, .22);
            opacity: 0; visibility: hidden; transform: translateY(8px);
            transition: opacity .2s, transform .2s, visibility .2s; z-index: 60;
        }
        .nav-user-menu:hover .nav-user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .nav-user-dropdown a, .nav-user-dropdown button {
            display: flex; align-items: center; gap: 10px; width: 100%;
            padding: 10px 12px; border: 0; background: none; border-radius: 9px;
            color: #1f2937; font-size: 13px; font-weight: 600; text-align: left; cursor: pointer;
        }
        .nav-user-dropdown a:hover, .nav-user-dropdown button:hover { background: #f1f5f9; color: #0b8a5a; }
        .nav-user-dropdown i { color: #64748b; width: 16px; text-align: center; }
        .nav-user-dropdown .dropdown-divider { height: 1px; background: #e5e7eb; margin: 6px 0; }

        /* HERO */
        .hero {
            position: relative;
            min-height: 640px;
            display: flex;
            align-items: center;
            padding: 150px 0 120px;
            color: #fff;
            background: #0b2f45 center/cover no-repeat;
            isolation: isolate;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0; z-index: -1;
            background: linear-gradient(90deg, rgba(4, 23, 35, .93), rgba(5, 30, 44, .72) 55%, rgba(5, 30, 44, .35));
        }
        .hero h1 { font-size: clamp(38px, 5.4vw, 60px); line-height: 1.08; font-weight: 800; letter-spacing: -2px; margin: 0 0 18px; }
        .hero h1 span { color: #4ade80; }
        .hero p { font-size: 17px; line-height: 1.7; color: #d5e3e8; max-width: 560px; margin: 0; }
        .hero-actions { display: flex; gap: 12px; margin-top: 28px; flex-wrap: wrap; }

        .quick-booking {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
            padding: 20px;
            margin-top: 40px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(2, 24, 35, .28);
        }
        .quick-field label { display: block; font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px; }
        .quick-field label i { color: var(--brand); margin-right: 5px; }
        .quick-field input, .quick-field select {
            width: 100%; height: 46px; padding: 0 13px;
            border: 1px solid var(--line); border-radius: 10px;
            background: #f9fafb; font: inherit; font-size: 14px; color: var(--ink);
            outline: none; transition: border .2s, box-shadow .2s;
        }
        .quick-field input:focus, .quick-field select:focus {
            border-color: var(--brand); box-shadow: 0 0 0 3px rgba(14, 163, 107, .12); background: #fff;
        }

        /* SECTIONS */
        .section { padding: 72px 0; }
        .section-alt { background: #fff; }
        .section-head { max-width: 640px; margin-bottom: 34px; }
        .eyebrow { display: inline-block; color: var(--brand); font-size: 12px; font-weight: 800; letter-spacing: 1.3px; text-transform: uppercase; margin-bottom: 8px; }
        .section-head h2 { font-size: 32px; line-height: 1.2; font-weight: 800; letter-spacing: -1px; margin: 0 0 10px; }
        .section-head p { color: var(--muted); font-size: 15px; line-height: 1.65; margin: 0; }
        .section-top { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 34px; }
        .section-link { color: var(--brand); font-size: 14px; font-weight: 700; white-space: nowrap; }
        .section-link:hover { color: var(--brand-dark); }

        .stats {
            display: grid; grid-template-columns: repeat(4, 1fr);
            padding: 44px 0 8px;
        }
        .stat { text-align: center; padding: 10px 20px; }
        .stat strong { display: block; font-size: 32px; font-weight: 800; letter-spacing: -1px; color: var(--ink); }
        .stat span { color: var(--muted); font-size: 13px; font-weight: 600; }

        /* COURT CARDS */
        .courts-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
        .pro-court-card {
            background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden;
            transition: transform .25s, box-shadow .25s;
        }
        .pro-court-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(15, 23, 42, .1); }
        .pro-court-image { position: relative; height: 210px; overflow: hidden; background: #e2e8f0; }
        .pro-court-image img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
        .pro-court-card:hover .pro-court-image img { transform: scale(1.05); }
        .court-image-fallback { height: 100%; display: grid; place-items: center; background: linear-gradient(135deg, #0b4b4d, #2acb78); color: #fff; font-size: 44px; }
        .court-tag, .court-rank { position: absolute; top: 12px; border-radius: 999px; padding: 5px 10px; font-size: 11px; font-weight: 800; }
        .court-tag { right: 12px; background: #fff; color: var(--brand-dark); }
        .court-rank { left: 12px; background: var(--ink); color: #fff; }
        .pro-court-body { padding: 18px; }
        .court-rating { font-size: 13px; font-weight: 700; color: #f59e0b; }
        .court-rating small { color: #94a3b8; font-weight: 600; }
        .pro-court-body h3 { margin: 8px 0 6px; font-size: 18px; font-weight: 700; letter-spacing: -.3px; }
        .court-location { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: 13px; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .court-location i { color: var(--brand); }
        .court-amenities-mini { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px; }
        .court-amenities-mini span { background: #f0fdf4; color: #15803d; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
        .court-card-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9; }
        .court-card-bottom small { display: block; color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .court-card-bottom strong { color: var(--brand); font-size: 17px; font-weight: 800; }
        .court-card-bottom strong span { color: #94a3b8; font-size: 11px; font-weight: 500; }
        .court-card-bottom button { background: none; border: 0; color: #0f172a; font-size: 13px; font-weight: 700; cursor: pointer; }
        .court-card-bottom button:hover { color: var(--brand); }

        /* PROMOTIONS */
        .promotion-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .promotion-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; }
        .promotion-visual { height: 130px; display: flex; align-items: flex-start; padding: 14px; background: linear-gradient(135deg, #053848, #00aa68); }
        .promotion-visual span { background: #fbbf24; color: #78350f; font-size: 11px; font-weight: 800; padding: 5px 9px; border-radius: 6px; }
        .promotion-card > div:last-child { padding: 18px; }
        .promotion-card small, .news-card small { color: #94a3b8; font-size: 12px; font-weight: 600; }
        .promotion-card h3 { margin: 8px 0; font-size: 17px; font-weight: 700; }
        .promotion-card p { color: var(--muted); font-size: 13px; line-height: 1.6; margin: 0 0 14px; }
        .promotion-card a, .news-card a { color: var(--brand); font-size: 13px; font-weight: 700; }

        /* CTA */
        .booking-cta {
            position: relative; overflow: hidden; border-radius: 20px; padding: 56px 60px;
            color: #fff; background: #0b2f45 center/cover no-repeat; isolation: isolate;
        }
        .booking-cta::before { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(90deg, rgba(4, 24, 37, .95), rgba(4, 24, 37, .6)); }
        .booking-cta span { color: #4ade80; font-size: 12px; font-weight: 800; letter-spacing: 1px; }
        .booking-cta h2 { font-size: 34px; font-weight: 800; letter-spacing: -1px; margin: 10px 0; }
        .booking-cta p { color: #d5e3e8; line-height: 1.7; max-width: 560px; }
        .booking-cta .btn-pill { margin-top: 8px; }

        /* NEWS */
        .news-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .news-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; }
        .news-image { height: 170px; display: grid; place-items: center; background: #dcfce7; color: #15803d; font-size: 34px; }
        .news-image img { width: 100%; height: 100%; object-fit: cover; }
        .news-card > div:last-child { padding: 18px; }
        .news-card h3 { margin: 8px 0; font-size: 16px; font-weight: 700; line-height: 1.4; }
        .news-card p { color: var(--muted); font-size: 13px; line-height: 1.6; margin: 0 0 14px; }

        /* REVIEWS */
        .review-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .review-card-pro { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 22px; }
        .review-card-top { display: flex; align-items: center; gap: 10px; }
        .review-avatar { width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center; background: #dcfce7; color: #15803d; font-weight: 800; }
        .review-card-top strong, .review-card-top small { display: block; font-size: 13px; }
        .review-card-top small { color: var(--muted); margin-top: 2px; }
        .review-card-top > span { margin-left: auto; align-self: flex-start; color: #94a3b8; font-size: 11px; }
        .review-stars { margin: 16px 0 10px; color: #fbbf24; font-size: 13px; }
        .review-card-pro p { margin: 0; color: #475569; font-size: 13px; line-height: 1.7; }

        /* WHY */
        .why-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .why-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 24px; }
        .why-icon { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 11px; background: #dcfce7; color: #15803d; font-size: 21px; margin-bottom: 16px; }
        .why-card h3 { font-size: 15px; font-weight: 700; margin: 0; }
        .why-card p { color: var(--muted); font-size: 13px; line-height: 1.6; margin: 8px 0 0; }

        /* FOOTER */
        .footer { background: #082536; color: #d5e2e5; padding: 56px 0 24px; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 36px; }
        .footer-brand { color: #fff; font-size: 20px; font-weight: 800; }
        .footer-brand img { height: 40px; border-radius: 8px; margin-right: 8px; vertical-align: middle; }
        .footer-grid p, .footer-grid a { display: block; color: #a8bdc4; font-size: 13px; line-height: 1.7; margin: 12px 0; }
        .footer-grid h4 { color: #fff; font-size: 13px; font-weight: 800; margin: 4px 0 14px; }
        .footer-bottom { margin-top: 34px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, .12); color: #8ca4ae; font-size: 12px; }

        .floating-book {
            position: fixed; right: 22px; bottom: 22px; z-index: 40;
            background: var(--brand); color: #fff; padding: 13px 18px; border-radius: 999px;
            font-size: 14px; font-weight: 700; box-shadow: 0 12px 28px rgba(14, 163, 107, .4);
        }
        .floating-book:hover { background: var(--brand-dark); color: #fff; }

        /* COURT DETAIL MODAL */
        .court-detail-modal .modal-dialog { max-width: 980px; }
        .court-detail-modal .modal-content { position: relative; overflow: hidden; border: 0; border-radius: 20px; box-shadow: 0 30px 80px rgba(6, 32, 44, .32); }
        .court-detail-modal .btn-close { position: absolute; top: 16px; right: 16px; z-index: 5; width: 38px; height: 38px; border-radius: 50%; background: #fff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center / 1.1em no-repeat; box-shadow: 0 6px 18px rgba(6, 32, 44, .18); opacity: 1; }
        .court-detail-modal .btn-close:hover { transform: rotate(90deg); }
        .court-detail-modal .btn-close, .court-detail-modal .btn-close:focus { transition: .25s; }
        .court-detail-modal .modal-body { padding: 0; }
        .cdm-grid { display: grid; grid-template-columns: 1.05fr 1fr; min-height: 480px; }
        .cdm-gallery { background: #0e2c3a; padding: 22px; }
        .cdm-main { position: relative; border-radius: 14px; overflow: hidden; height: 340px; background: #0a2230; }
        .cdm-main img { width: 100%; height: 100%; object-fit: cover; transition: .4s; }
        .cdm-fallback { height: 100%; display: grid; place-items: center; background: linear-gradient(135deg, #0b4b4d, #2acb78); color: #fff; font-size: 56px; }
        .cdm-type { position: absolute; left: 14px; bottom: 14px; border-radius: 100px; background: rgba(6, 32, 44, .85); color: #eaffee; padding: 7px 12px; font-size: 12px; font-weight: 700; }
        .cdm-thumbs { display: flex; gap: 10px; margin-top: 12px; overflow-x: auto; padding-bottom: 2px; }
        .cdm-thumb { flex: 0 0 86px; height: 64px; padding: 0; border: 2px solid transparent; border-radius: 10px; overflow: hidden; background: transparent; opacity: .62; transition: .2s; cursor: pointer; }
        .cdm-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .cdm-thumb.active, .cdm-thumb:hover { border-color: #34e699; opacity: 1; }
        .cdm-info { padding: 30px 28px 8px; overflow-y: auto; }
        .cdm-kicker { display: inline-block; margin-bottom: 8px; color: var(--brand); font-size: 12px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
        .cdm-info h2 { margin: 0 0 12px; color: var(--ink); font-size: 26px; letter-spacing: -1px; line-height: 1.2; font-weight: 800; }
        .cdm-rating { display: flex; align-items: center; gap: 7px; margin-bottom: 18px; }
        .cdm-rating .stars { color: #f6b725; font-size: 13px; }
        .cdm-rating > span { color: var(--ink); font-weight: 800; font-size: 14px; }
        .cdm-rating small { color: #94a3b8; font-size: 12px; font-weight: 600; }
        .cdm-contact { display: flex; flex-direction: column; gap: 10px; padding: 14px; border-radius: 12px; background: #f4f8f6; }
        .cdm-contact p { display: flex; gap: 10px; margin: 0; color: #536771; font-size: 13px; line-height: 1.5; }
        .cdm-contact i { margin-top: 2px; color: var(--brand); font-size: 14px; }
        .cdm-contact strong { color: #203a4b; }
        .cdm-contact span { flex: 1; }
        .cdm-amenities h3, .cdm-desc h3 { display: flex; align-items: center; gap: 8px; margin: 22px 0 10px; color: var(--ink); font-size: 14px; font-weight: 800; }
        .cdm-amenities h3::before, .cdm-desc h3::before { content: ''; width: 4px; height: 16px; border-radius: 3px; background: #08d67c; }
        .cdm-amenities > div { display: flex; flex-wrap: wrap; gap: 7px; }
        .cdm-amenities span { display: inline-flex; align-items: center; gap: 5px; border-radius: 100px; background: #edf9f2; color: #3e6d5c; padding: 6px 10px; font-size: 12px; font-weight: 700; }
        .cdm-amenities i { color: var(--brand); font-size: 12px; }
        .cdm-desc p { margin: 0; color: #5b6f7a; font-size: 13px; line-height: 1.7; }
        .cdm-footer { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 16px 28px; border-top: 1px solid #edf1ef; background: #fff; }
        .cdm-footer small { display: block; color: #94a3b8; font-size: 11px; font-weight: 800; letter-spacing: .4px; }
        .cdm-footer strong { display: block; color: var(--brand); font-size: 24px; letter-spacing: -.5px; }
        .cdm-footer em { color: #94a3b8; font-size: 12px; font-style: normal; font-weight: 600; }
        .cdm-footer .btn { display: inline-flex; align-items: center; gap: 8px; border-radius: 12px; padding: 13px 22px; background: var(--brand); color: #fff; font-weight: 800; font-size: 14px; box-shadow: 0 10px 22px rgba(14, 163, 107, .35); }
        .cdm-footer .btn:hover { background: var(--brand-dark); }

        /* RESPONSIVE */
        @media (max-width: 991px) {
            .nav-links { display: none; }
            .cdm-grid { grid-template-columns: 1fr; min-height: 0; }
            .cdm-main { height: 230px; }
            .cdm-info { padding: 22px 18px 6px; }
            .cdm-footer { flex-direction: column; padding: 14px 18px 18px; }
            .cdm-footer .btn { width: 100%; justify-content: center; }
            .quick-booking { grid-template-columns: repeat(2, 1fr); }
            .courts-grid, .promotion-grid, .news-grid, .review-grid { grid-template-columns: repeat(2, 1fr); }
            .why-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .hero { min-height: 580px; padding: 130px 0 100px; }
            .hero h1 { font-size: 36px; letter-spacing: -1px; }
            .quick-booking { grid-template-columns: 1fr; }
            .stats { grid-template-columns: repeat(2, 1fr); gap: 24px; }
            .courts-grid, .promotion-grid, .news-grid, .review-grid { grid-template-columns: 1fr; }
            .why-grid { grid-template-columns: 1fr; }
            .booking-cta { padding: 38px 26px; }
            .booking-cta h2 { font-size: 27px; }
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 26px; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<header class="nav" id="nav">
    <div class="container-wide nav-inner">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="SmashZone logo">
        </a>
        <nav class="nav-links">
            <a href="#home">Trang chủ</a>
            <a href="{{ route('courts.index') }}">Sân cầu lông</a>
            <a href="#offers">Khuyến mãi</a>
            <a href="#news">Tin tức</a>
            <a href="#why">Giới thiệu</a>
        </nav>
        <div class="nav-actions">
            @auth
                <div class="nav-user-menu">
                    <button type="button" class="nav-user-trigger">
                        <i class="bi bi-person-circle"></i> {{ Str::limit(Auth::user()->name, 16) }}
                        <i class="bi bi-chevron-down caret"></i>
                    </button>
                    <div class="nav-user-dropdown">
                        <a href="{{ route('profile') }}"><i class="bi bi-person"></i> Thông tin tài khoản</a>
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button>
                        </form>
                    </div>
                </div>
            @else
                <a class="nav-user" href="{{ route('login') }}">Đăng nhập</a>
            @endauth
            <a class="btn-pill btn-primary" href="{{ $primaryRoute }}">{{ $primaryLabel }}</a>
        </div>
    </div>
</header>

<main id="home">
    <!-- HERO -->
    <section class="hero" @if($heroImage) style="background-image:url('{{ $heroImage }}')" @endif>
        <div class="container-wide">
            <h1>Đặt sân cầu lông<br><span>nhanh chóng và tiện lợi.</span></h1>
            <p>Tìm sân, chọn khung giờ yêu thích và đặt sân chỉ trong vài phút cùng SmashZone.</p>
            <div class="hero-actions">
                <a class="btn-pill btn-primary" href="{{ $primaryRoute }}">{{ $primaryLabel }} <i class="bi bi-arrow-right"></i></a>
                <a class="btn-pill btn-ghost" href="#featured">Khám phá sân</a>
            </div>

            @if($canBook)
            <form class="quick-booking" action="{{ route('courts.index') }}" method="GET">
                <div class="quick-field">
                    <label><i class="bi bi-geo-alt"></i>Khu vực</label>
                    <input name="keyword" placeholder="Tên sân hoặc địa điểm">
                </div>
                <div class="quick-field">
                    <label><i class="bi bi-calendar3"></i>Ngày</label>
                    <input type="date" name="booking_date" min="{{ now()->toDateString() }}">
                </div>
                <div class="quick-field">
                    <label><i class="bi bi-clock"></i>Khung giờ</label>
                    <select name="time_slot_id">
                        <option value="">Chọn giờ</option>
                        @foreach($timeSlots as $slot)
                            <option value="{{ $slot->id }}">{{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="quick-field">
                    <label><i class="bi bi-wallet2"></i>Khoảng giá</label>
                    <select name="price_max">
                        <option value="">Mọi mức giá</option>
                        <option value="100000">Dưới 100.000đ</option>
                        <option value="150000">Dưới 150.000đ</option>
                        <option value="200000">Dưới 200.000đ</option>
                    </select>
                </div>
                <button type="submit" class="btn-pill btn-primary">Tìm sân <i class="bi bi-arrow-right"></i></button>
            </form>
            @endif
        </div>
    </section>

    <!-- STATS -->
    <div class="container-wide">
        <div class="stats">
            <div class="stat"><strong data-counter="{{ $statistics['bookings'] }}" data-suffix="+">{{ $statistics['bookings'] }}+</strong><span>Lượt đặt sân</span></div>
            <div class="stat"><strong data-counter="{{ $statistics['courts'] }}" data-suffix="+">{{ $statistics['courts'] }}+</strong><span>Sân hoạt động</span></div>
            <div class="stat"><strong data-counter="{{ $statistics['customers'] }}" data-suffix="+">{{ $statistics['customers'] }}+</strong><span>Khách hàng</span></div>
            <div class="stat"><strong>{{ $statistics['rating'] ?: '—' }}</strong><span>Đánh giá trung bình</span></div>
        </div>
    </div>

    <!-- FEATURED COURTS -->
    <section class="section" id="featured">
        <div class="container-wide">
            <div class="section-top">
                <div class="section-head" style="margin-bottom:0;">
                    <span class="eyebrow">Sân nổi bật</span>
                    <h2>Được khách hàng yêu thích</h2>
                    <p>Những sân được cộng đồng SmashZone lựa chọn nhiều nhất.</p>
                </div>
                <a class="section-link" href="{{ route('courts.index') }}">Xem tất cả <i class="bi bi-arrow-right"></i></a>
            </div>
            @if($featured_courts->isNotEmpty())
                <div class="courts-grid">
                    @foreach($featured_courts as $court)
                        <x-court-card :court="$court" :modal-id="'featuredCourt'.$court->id"/>
                        @include('components.court-detail-modal', ['court' => $court, 'modalId' => 'featuredCourt'.$court->id])
                    @endforeach
                </div>
            @else
                <p class="text-muted">Hiện chưa có sân phù hợp.</p>
            @endif
        </div>
    </section>

    <!-- PROMOTIONS -->
    @if($promotions->isNotEmpty())
    <section class="section section-alt" id="offers">
        <div class="container-wide">
            <div class="section-head">
                <span class="eyebrow">Ưu đãi</span>
                <h2>Ưu đãi dành cho bạn</h2>
                <p>Sẵn sàng cho những trận cầu nhiều cảm hứng với các ưu đãi đang diễn ra.</p>
            </div>
            <div class="promotion-grid">
                @foreach($promotions->take(3) as $promotion)
                    <x-promotion-card :promotion="$promotion"/>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- BOOKING CTA -->
    @if($canBook)
    <section class="section">
        <div class="container-wide">
            <div class="booking-cta" @if($heroImage) style="background-image:url('{{ $heroImage }}')" @endif>
                <span>SMASHZONE BOOKING</span>
                <h2>Trận đấu tiếp theo của bạn đã sẵn sàng.</h2>
                <p>Chọn sân yêu thích và bắt đầu đặt sân chỉ trong vài phút.</p>
                <a class="btn-pill btn-primary" href="{{ route('courts.index') }}">Đặt sân ngay <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </section>
    @endif

    <!-- NEWS -->
    @if($news->isNotEmpty())
    <section class="section section-alt" id="news">
        <div class="container-wide">
            <div class="section-head">
                <span class="eyebrow">Cộng đồng</span>
                <h2>Tin tức & Cẩm nang cầu lông</h2>
                <p>Cập nhật mẹo chơi, hoạt động và thông tin hữu ích từ SmashZone.</p>
            </div>
            <div class="news-grid">
                @foreach($news->take(3) as $item)
                    <x-news-card :news="$item"/>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- REVIEWS -->
    @if($reviews->isNotEmpty())
    <section class="section">
        <div class="container-wide">
            <div class="section-head">
                <span class="eyebrow">Trải nghiệm</span>
                <h2>Khách hàng nói gì về SmashZone?</h2>
                <p>Những chia sẻ thật từ cộng đồng người chơi của chúng tôi.</p>
            </div>
            <div class="review-grid">
                @foreach($reviews->take(3) as $review)
                    <x-review-card :review="$review"/>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- WHY -->
    <section class="section section-alt" id="why">
        <div class="container-wide">
            <div class="section-head">
                <span class="eyebrow">SmashZone</span>
                <h2>Tại sao chọn SmashZone?</h2>
                <p>Một trải nghiệm đơn giản, minh bạch và sẵn sàng cho mọi trận đấu.</p>
            </div>
            <div class="why-grid">
                <div class="why-card"><i class="why-icon bi bi-lightning-charge"></i><h3>Đặt sân nhanh</h3><p>Chọn sân và khung giờ yêu thích chỉ trong vài bước.</p></div>
                <div class="why-card"><i class="why-icon bi bi-clock-history"></i><h3>Cập nhật thời gian thực</h3><p>Biết ngay khung giờ nào còn trống trước khi đặt.</p></div>
                <div class="why-card"><i class="why-icon bi bi-shield-check"></i><h3>Thanh toán tiện lợi</h3><p>Quy trình thanh toán rõ ràng, nhanh chóng và an toàn.</p></div>
                <div class="why-card"><i class="why-icon bi bi-calendar2-check"></i><h3>Quản lý dễ dàng</h3><p>Theo dõi lịch đặt sân của bạn bất cứ lúc nào.</p></div>
            </div>
        </div>
    </section>
</main>

<!-- FOOTER -->
<footer class="footer">
    <div class="container-wide">
        <div class="footer-grid">
            <div>
                <a class="footer-brand" href="{{ route('home') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="SmashZone logo">
                </a>
                <p>Nền tảng đặt sân cầu lông đơn giản, nhanh chóng và tiện lợi.</p>
            </div>
            <div>
                <h4>Khám phá</h4>
                <a href="#home">Trang chủ</a>
                <a href="{{ route('courts.index') }}">Sân cầu lông</a>
                <a href="#offers">Khuyến mãi</a>
                <a href="#news">Tin tức</a>
            </div>
            <div>
                <h4>Hỗ trợ</h4>
                <a href="#">Liên hệ</a>
                <a href="#">Câu hỏi thường gặp</a>
                <a href="#">Điều khoản</a>
                <a href="#">Chính sách</a>
            </div>
            <div>
                <h4>Liên hệ</h4>
                <p>Hotline: 0982 949 974<br>Email: hello@smashzone.vn<br>Hà Nội, Việt Nam</p>
            </div>
        </div>
        <div class="footer-bottom">© {{ now()->year }} SmashZone. All rights reserved.</div>
    </div>
</footer>

<a class="floating-book" href="{{ $primaryRoute }}"><i class="bi {{ $canBook ? 'bi-calendar2-plus' : 'bi-speedometer2' }}"></i> {{ $primaryLabel }}</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
<script>
    const nav = document.getElementById('nav');
    window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 24), { passive: true });

    document.querySelectorAll('[data-counter]').forEach((el) => {
        const target = Number(el.dataset.counter);
        if (!Number.isFinite(target) || target === 0) return;
        const suffix = el.dataset.suffix || '';
        let started = false;
        new IntersectionObserver((entries) => {
            if (!entries[0].isIntersecting || started) return;
            started = true;
            const start = performance.now();
            const duration = 700;
            const tick = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                el.textContent = Math.round(target * progress).toLocaleString('vi-VN') + suffix;
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }).observe(el);
    });
</script>
@auth
    @if((Auth::user()->role ?: 'CUSTOMER') === 'CUSTOMER')
        @include('partials.ai-chatbot')
    @endif
@endauth
</body>
</html>
