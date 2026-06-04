@component('mail::message')
# New cookies on {{ $domain->hostname }}

A scan finished and detected **{{ count($cookies) }} new or unclassified cookie(s)**.
@if ($unclassifiedCount > 0)
{{ $unclassifiedCount }} of those need a category before they can be gated correctly.
@endif

@component('mail::table')
| Name | Source | Category |
| ---- | ------ | -------- |
@foreach ($cookies as $cookie)
| `{{ $cookie['name'] }}` | {{ $cookie['sourceDomain'] ?? '1st party' }} | {{ $cookie['category'] }} |
@endforeach
@endcomponent

@component('mail::button', ['url' => url('/domains/'.$domain->domain_uid)])
Review cookies
@endcomponent

You can disable these alerts in the domain settings.

Thanks,
{{ config('app.name') }}
@endcomponent
