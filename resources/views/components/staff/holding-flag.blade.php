@props(['flag'])

{{-- A list row's note that the applicant already holds a scholarship elsewhere (see ScholarshipHoldingService). --}}
@if ($flag['national'])
    <span class="border-l-2 border-crimson-600 pl-1 font-body text-[10px] font-600 uppercase tracking-[0.06em] text-crimson-600" title="Already holds a national scholarship, so this application can't be awarded">National grantee</span>
@elseif ($flag['held'] > 0)
    <span class="border-l-2 border-gold-600 pl-1 font-body text-[10px] font-600 uppercase tracking-[0.06em] text-gold-600" title="Holds {{ $flag['held'] }} {{ str('scholarship')->plural($flag['held']) }} in other programs">Grantee elsewhere</span>
@endif
