@props(['feature', 'plan' => null])

@feature($feature, $plan)
    {{ $slot }}
@endfeature
