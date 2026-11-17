{{-- Analytics for the public demo only (BILLTRIX_DEMO_MODE + BILLTRIX_DEMO_GTM_ID). Client installs never load this. --}}
@if (config('billtrix.demo.enabled') && ($gtm = config('billtrix.demo.gtm_id')))
<script>
    window.dataLayer = window.dataLayer || [];
    @if ($signup = session('demo_signup'))
    window.dataLayer.push(Object.assign({ event: 'demo_signup' }, @json($signup)));
    @endif
</script>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtm));</script>
@endif