<x-layouts.portal
    :title="'Apply'"
    heading="Choose Your Program"
    subheading="Select the opportunity that best matches your goals and continue your application journey."
    portalRole="{{ auth()->check() && auth()->user()->hasRole('admin') ? 'admin' : (auth()->check() && auth()->user()->hasRole('employer') ? 'employer' : 'jobseeker') }}"
>
    @include('public.partials._program_cards', ['programs' => $programs, 'showActions' => true])

    @include('public.partials._why_choose_us')
</x-layouts.portal>
