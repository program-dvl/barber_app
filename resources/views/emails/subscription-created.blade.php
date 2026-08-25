<x-mail::message>
# Hey {{ $user->name }},

Welcome to {{ config('brand.product_name') }}!

Your subscription has been created successfully. You can now access all the features of our platform.

<x-mail::button :url="config('brand.website_url')">
    Visit Website
</x-mail::button>

Thanks,<br>
The {{ config('brand.product_name') }} team
</x-mail::message>
