@props(['rows'])
<div class="admin-pagination mt-3">{{ $rows->withQueryString()->links() }}</div>
