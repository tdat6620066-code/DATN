@extends('layouts.app')
@section('title','Sân yêu thích — SmashZone')
@section('content')
<x-customer-shell active="favorites"><div class="customer-favorites">

    <x-customer-page-heading eyebrow="YOUR COLLECTION" title="Sân dành riêng cho bạn" description="Giữ lại những sân yêu thích, sẵn sàng cho lần đặt tiếp theo."/>

    @if (session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif

    <div class="row">

        @forelse ($favorites as $favorite)

            @php
                $court = $favorite->court;
            @endphp

            @if ($court)

                <div class="col-md-4 mb-4">

                    <div class="card h-100 shadow-sm">

                        @if ($court->image ?? false)

                            <img
                                src="{{ asset('storage/' . $court->image) }}"
                                class="card-img-top"
                                style="height:220px;object-fit:cover;"
                            >

                        @endif

                        <div class="card-body">

                            <h5 class="fw-bold">
                                {{ $court->name }}
                            </h5>

                            <form
                                method="POST"
                                action="{{ route('favorites.destroy', $court) }}"
                            >

                                @csrf

                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="btn btn-outline-danger"
                                >
                                    Bỏ yêu thích
                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            @endif

        @empty

            <div class="col-12">

                <div class="alert alert-info">
                    Bạn chưa có sân yêu thích.
                </div>

            </div>

        @endforelse

    </div>

    {{ $favorites->links() }}

</div>

</x-customer-shell>
@endsection
