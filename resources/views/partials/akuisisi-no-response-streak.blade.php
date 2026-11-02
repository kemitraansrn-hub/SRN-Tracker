{{-- Penanda lead yang berulang kali No Respon di tahapan yang sama. Expects: $lead --}}
@if ($lead->no_response_streak >= \App\Support\AkuisisiPipelineRules::NO_RESPONSE_LIMIT)
    <span class="chip chip-critical" title="{{ $lead->no_response_streak }}&times; No Respon berturut-turut di tahap {{ $lead->pipelineStage?->name }}. Pertimbangkan lanjut atau tandai Not Interested.">
        {{ $lead->no_response_streak }}&times; tidak merespons
    </span>
@endif
