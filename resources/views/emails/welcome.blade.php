<x-mail::message>
# Hey {{ $user->name }},

Welcome to {{ config('brand.product_name') }}. Your next step is to verify your account and set up the rules your team will use to run the day.

<x-mail::button :url="config('brand.website_url')">
    Visit Website
</x-mail::button>

Thanks,<br>
The {{ config('brand.product_name') }} team
</x-mail::message>
