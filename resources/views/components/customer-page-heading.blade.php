@props(['eyebrow' => 'SMASHZONE / YOUR GAME', 'title', 'description' => ''])
<header class="customer-page-heading"><div><span>{{ $eyebrow }}</span><h1>{{ $title }}</h1>@if($description)<p>{{ $description }}</p>@endif</div>@if($slot->isNotEmpty())<div class="customer-page-heading-action">{{ $slot }}</div>@endif</header>
