@php
    $studentQuestions = [
        ['I can’t find a scholarship I heard about.', 'Browse shows only scholarships open for applications right now. If it hasn’t opened yet, or has closed or reached its maximum number of applications, it isn’t listed. You are notified when a scholarship opens.'],
        ['The Apply button is missing on a scholarship page.', 'Either you already applied (the page shows View your application), or you hold a national scholarship and this one is national too. The page says which.'],
        ['My application won’t submit.', 'Read the message under the highlighted field. The most common causes are a missing required field, a file that is too big or the wrong type, a GWA below the required GWA, or a family income above the scholarship’s limit.'],
        ['I made a mistake in my submitted application.', 'A submitted application can’t be edited. Contact the OSDW office; staff can reject a document with remarks so you know what to correct.'],
        ['My name or birthdate is wrong.', 'Open Settings from your initials and correct it. Your Student ID and email can only be corrected by OSDW.'],
        ['A requirement says Returned.', 'Read the remarks under it, fix the file, and upload it again on your application page.'],
        ['I uploaded the wrong renewal file.', 'Renewal and disbursement uploads are submitted once. Ask OSDW to return it, then upload the correct file.'],
        ['I didn’t get the email code.', 'Check Spam or Promotions, make sure the email is spelled right, wait 60 seconds and send a new code.'],
        ['Can I apply to more than one scholarship?', 'Yes. The only limit is one national scholarship at a time.'],
        ['Where can I see how much the award is?', 'Award amounts aren’t shown on public pages. OSDW tells awardees the details.'],
        ['I don’t want my name published.', 'Leave the publication consent unticked when you apply. You are counted in the results but not named.'],
    ];
    $staffQuestions = [
        ['Open shortlist requirements is disabled.', 'It enables once shortlisted plus awarded applicants reach the offering’s slots. Endorse more applicants first.'],
        ['Award scholarship is disabled.', 'The message beside it says why: shortlist requirements still to comply with, no slots left, or the applicant already holds a national scholarship.'],
        ['Publish results or Disburse all is disabled.', 'Both need every slot awarded. Disburse all also needs every grantee’s disbursement requirements complied with: uploads verified, physical copies received.'],
        ['Renew scholarship is disabled.', 'The scholar hasn’t entered their year level, or a renewal requirement isn’t complied with yet.'],
        ['I can’t delete an offering or program.', 'Anything holding applications or renewals can’t be deleted, and neither can an offering another renewal offering continues.'],
        ['A staff member forgot their password.', 'An administrator opens Users and selects Reset password. The new temporary password is shown once.'],
        ['I keep landing on the setup page.', 'Finish both steps: set your own password and, if Google sign-in is on, connect your Google account.'],
        ['The report printout or the guide prints with the page address on it.', 'In the print window, open More settings and untick Headers and footers.'],
    ];
@endphp

<x-help.chapter :id="$chapter['id']" :number="$number" :title="$chapter['title']" :icon="$chapter['icon']">

    <x-help.topic id="faq-students" title="Questions from students">
        <div class="mt-2 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
            @foreach ($studentQuestions as [$question, $answer])
                <div data-help-keep class="px-4 py-3.5">
                    <p class="flex gap-2 font-body text-[14px] font-600 leading-6 text-ink"><x-ui.icon name="help" class="mt-[3px] size-4 text-navy-600" />{{ $question }}</p>
                    <p class="mt-1 pl-6 font-body text-[14px] leading-6 text-slate">{{ $answer }}</p>
                </div>
            @endforeach
        </div>
    </x-help.topic>

    <x-help.topic id="faq-staff" title="Questions from staff and administrators">
        <div class="mt-2 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
            @foreach ($staffQuestions as [$question, $answer])
                <div data-help-keep class="px-4 py-3.5">
                    <p class="flex gap-2 font-body text-[14px] font-600 leading-6 text-ink"><x-ui.icon name="help" class="mt-[3px] size-4 text-navy-600" />{{ $question }}</p>
                    <p class="mt-1 pl-6 font-body text-[14px] leading-6 text-slate">{{ $answer }}</p>
                </div>
            @endforeach
        </div>
    </x-help.topic>

    <x-help.topic id="faq-contact" title="Contacting OSDW">
        <p>For anything this guide doesn’t answer, such as a wrong Student ID, an account problem or a question about your application, contact the Office of Student Development and Welfare. Have your Student ID ready.</p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach ([['mail', 'Email', 'osdwaparri@csu.edu.ph', false], ['chat', 'Facebook', '@OSDWAparri', false], ['phone', 'Phone', '0953-554-2328', true]] as [$icon, $label, $value, $mono])
                <div data-help-keep class="flex items-center gap-3 rounded-card border border-line bg-surface p-4">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-control bg-navy-50 text-navy-700"><x-ui.icon :name="$icon" class="size-[18px]" /></span>
                    <div class="min-w-0">
                        <p class="font-body text-[12px] text-mist">{{ $label }}</p>
                        <p @class(['break-all text-[14px] font-500 text-ink', 'font-mono' => $mono, 'font-body' => ! $mono])>{{ $value }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </x-help.topic>

</x-help.chapter>
