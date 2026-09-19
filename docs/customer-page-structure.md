# Customer page structure

## Scope

Customer presentation only. No controller, model, database, route, pricing or payment changes. Existing form actions, field names, CSRF directives and business conditions retained.

## Changes

- Court listing: collapsible Bootstrap filter on mobile; existing filter form retained.
- Court detail: gallery before court information; desktop gallery with vertical thumbnails and prominent booking panel.
- Booking selection: primary page heading clarified; existing availability and submission controls retained.
- Booking history: responsive card collection.
- Customer overview: upcoming sessions before summary metrics.
- Account/password: explanatory column beside existing form.
- Favorites and notification preferences: shared Customer layout and navigation.
- Notifications: date grouping within the current paginated result.
- Support: separate compose and conversation regions; conversation stream styling.
- Checkout: wider details column and separate order summary styling; existing payment form retained.
- Mobile: persistent navigation to existing routes, with page padding and chatbot offset.

## Files created

- public/css/customer-pages.css
- resources/views/components/customer-page-heading.blade.php
- resources/views/partials/customer-mobile-nav.blade.php
- docs/customer-page-structure.md

## Views modified

- layouts/app.blade.php
- partials/site-header.blade.php
- components/customer-shell.blade.php
- courts/index.blade.php
- courts/show.blade.php
- bookings/create.blade.php
- bookings/index.blade.php
- profile/index.blade.php
- profile/notification-settings.blade.php
- favorites/index.blade.php
- notifications/index.blade.php
- contacts/index.blade.php
- contacts/show.blade.php

## Validation

- Blade compilation successful.
- Static UI audit: zero missing literal routes, placeholder links, duplicate extends or POST forms without local CSRF.
- CustomerDashboardTest, CourtUiTest, BookingCheckoutUiTest, CommunicationUiTest, CustomerReviewTest, DailyBookingDurationTest: 29 tests / 258 assertions passed.
- Production Vite build successful.
- Headless Edge: homepage and court listing at 375, 768, 1440 and 1920px; no horizontal overflow, broken images or JavaScript exceptions. Mobile navigation toggle opened successfully.
- This is not a full authenticated browser audit of every customer state. Recurring booking and checkout business forms were retained rather than rewritten.
