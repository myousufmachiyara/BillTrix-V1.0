@if (config('billtrix.demo.enabled') && ($gtm = config('billtrix.demo.gtm_id')))
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif