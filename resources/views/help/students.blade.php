<x-help.chapter :id="$chapter['id']" :number="$number" :title="$chapter['title']" :icon="$chapter['icon']">

    <x-help.topic id="st-register" title="Create your account">
        <p>You need your own account to apply. Each account belongs to one email address, so use one you can open.</p>

        <x-help.screen url="/register" caption="Registering: the 6-digit code is sent to your email and entered here." :student="false">
            <div class="mx-auto max-w-[300px]">
                <x-help.mock.card>
                    <div class="space-y-3 p-4 text-center">
                        <span class="mx-auto flex size-8 items-center justify-center rounded-full bg-navy-50 text-navy-700"><x-ui.icon name="mail" class="size-4" /></span>
                        <div>
                            <p class="font-display text-[14px] font-600">Check your email</p>
                            <p class="text-[10px] text-slate">Enter the 6-digit code sent to <span class="font-500 text-ink">juan.delacruz@gmail.com</span></p>
                        </div>
                        <div class="flex items-center justify-center gap-1">
                            @foreach (['4', '8', '1', '9', '0', ''] as $digit)
                                <span @class(['flex h-8 w-6 items-center justify-center rounded-[6px] border bg-surface font-mono text-[13px]', 'border-navy-600 ring-2 ring-navy-600/20' => $digit === '', 'border-line' => $digit !== ''])>{{ $digit }}</span>
                            @endforeach
                            <x-help.marker n="2" class="ml-1" />
                        </div>
                        <p class="text-[10px] text-mist">Send a new code in <span class="font-mono">0:42</span></p>
                    </div>
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Select <strong>Register</strong> at the top of any page. Enter your email address and select <strong>Send code</strong>.</x-help.step>
            <x-help.step n="2">Open your email and type the <strong>6-digit code</strong>. It expires in 10 minutes, and after 5 wrong tries you need a new one. You can ask for a new code after 60 seconds.</x-help.step>
            <x-help.step n="3">Choose a <strong>password</strong> of at least 8 characters. This is your password for this system only, not your email’s.</x-help.step>
            <x-help.step n="4">You are signed in and taken to <a href="#st-profile" class="font-500 text-navy-700 hover:underline">set up your profile</a>.</x-help.step>
        </x-help.steps>

        <x-help.note tone="tip" title="Faster with Google">
            <p>If you see <strong>Continue with Google</strong>, you can use your Google account instead. No code is needed because Google has already confirmed your email. If you already have an account with that email, Google is linked to it and no second account is made.</p>
        </x-help.note>
        <x-help.note tone="warning" title="Didn’t get the code?">
            <p>Check your Spam or Promotions folder, make sure the email is spelled right, then send a new code.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="st-profile" title="Set up your profile">
        <p>Right after registering, you set up your profile once. Until it is done, every student page brings you back here.</p>

        <x-help.screen url="/account/profile" caption="Setting up your profile. Your Student ID can’t be changed after this." :student="false">
            <div class="mx-auto max-w-[460px]">
                <x-help.mock.card title="Set up your profile" icon="user">
                    <div class="grid grid-cols-2 gap-2.5 p-3">
                        <div class="col-span-2 flex items-end gap-1.5"><x-help.mock.field label="Student ID" value="21-01234" mono class="flex-1" /><x-help.marker n="1" class="mb-1" /></div>
                        <x-help.mock.field label="First name" value="Juan" />
                        <x-help.mock.field label="Last name" value="Dela Cruz" />
                        <x-help.mock.field label="Sex" value="Male" select />
                        <x-help.mock.field label="Birthdate" value="2004-03-15" mono />
                        <x-help.mock.field label="Mobile number" value="0917 123 4567" mono />
                        <x-help.mock.field label="Address" value="Brgy. Centro, Aparri" />
                        <div class="col-span-2 flex justify-end"><x-help.mock.button>Save profile</x-help.mock.button></div>
                    </div>
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Enter your <strong>Student ID</strong> exactly as it appears on your school ID. Check it twice: it is how OSDW knows you across every scholarship and term, and it <strong>can’t be changed</strong> later.</x-help.step>
            <x-help.step n="2">Fill in your name, sex, birthdate, mobile number and address, then save. These are filled in for you on every application.</x-help.step>
        </x-help.steps>

        <x-help.note tone="rule" title="“This Student ID is already registered”">
            <p>Each Student ID can belong to one account only. If yours is taken, or you typed it wrong, contact the OSDW office. Don’t create another account.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="st-browse" title="Find a scholarship">
        <p>Select <strong>Browse</strong>. The list shows only scholarships <strong>open for applications right now</strong>. Closed and upcoming ones don’t appear.</p>

        <x-help.screen url="/programs" caption="Browse: every open scholarship, with filters by provider and type." active="Browse">
            <div class="flex flex-wrap items-center gap-2">
                <p class="mr-auto font-display text-[15px] font-600">Scholarship programs</p>
                <x-help.mock.field label="" value="All providers" select class="w-[110px] [&>p]:hidden" />
                <x-help.mock.field label="" value="All types" select class="w-[100px] [&>p]:hidden" />
                <x-help.marker n="1" />
            </div>
            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ([['CHED Merit Scholarship', 'CHED', 'Academic', 'Oct 31', true], ['Provincial Educational Assistance', 'Province of Cagayan', 'Non-academic', 'Nov 15', false]] as [$name, $provider, $type, $due, $soon])
                    <x-help.mock.card>
                        <div class="space-y-1.5 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-display text-[12px] font-600 leading-snug">{{ $name }}</p>
                                @if ($soon)<x-help.mock.stamp tone="amber">Closing soon</x-help.mock.stamp>@endif
                            </div>
                            <p class="text-[10px] text-slate">{{ $provider }} · {{ $type }}</p>
                            <div class="flex items-center justify-between pt-1">
                                <span class="flex items-center gap-1 text-[10px] text-slate"><x-ui.icon name="calendar" class="size-3" /> Deadline {{ $due }}</span>
                                <span class="flex items-center gap-1 text-[10px] font-500 text-navy-700">View details <x-ui.icon name="arrow-right" class="size-3" />@if ($soon)<x-help.marker n="2" class="ml-0.5" />@endif</span>
                            </div>
                        </div>
                    </x-help.mock.card>
                @endforeach
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Narrow the list by <strong>Provider</strong> or <strong>Type of scholarship</strong>. <strong>Clear filters</strong> shows everything again.</x-help.step>
            <x-help.step n="2">Open a scholarship to read its description, eligibility, requirements, slots, opening date and deadline, and its announcements. Then select <strong>Apply now</strong>.</x-help.step>
        </x-help.steps>

        <p class="mt-4">If you already applied, the page shows <strong>View your application</strong> and your status instead of Apply now. Public pages never show award amounts.</p>
    </x-help.topic>

    <x-help.topic id="st-apply" title="Apply for a scholarship">
        <p>The application form has three parts, then a review step before you submit.</p>

        <x-help.screen url="/programs/12/apply" caption="The application form: your identity from your profile, your academic standing, then what this scholarship asks for." active="Browse">
            <p class="font-display text-[14px] font-600">Apply · CHED Merit Scholarship</p>
            <div class="mt-3 space-y-2.5">
                <x-help.mock.card title="Your identity" icon="user">
                    <x-slot:meta><x-help.marker n="1" /></x-slot:meta>
                    <div class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-3">
                        <x-help.mock.field label="Student ID" value="21-01234" mono readonly />
                        <x-help.mock.field label="Name" value="Juan Dela Cruz" readonly />
                        <x-help.mock.field label="Email" value="juan.delacruz@gmail.com" readonly class="hidden sm:block" />
                    </div>
                </x-help.mock.card>
                <x-help.mock.card title="Academic standing" icon="graduation">
                    <x-slot:meta><x-help.marker n="2" /></x-slot:meta>
                    <div class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-4">
                        <x-help.mock.field label="College" value="College of ICS" select />
                        <x-help.mock.field label="Year level" value="2nd year" select />
                        <x-help.mock.field label="GWA" value="1.45" mono />
                        <x-help.mock.field label="Family monthly income" value="₱ 18,000" mono />
                    </div>
                </x-help.mock.card>
                <x-help.mock.card title="Program requirements" icon="clipboard-check">
                    <x-slot:meta><x-help.marker n="3" /></x-slot:meta>
                    <div class="space-y-2 p-3">
                        <div class="flex items-center gap-2 rounded-[6px] border border-dashed border-line bg-paper px-2.5 py-2">
                            <x-ui.icon name="upload" class="size-3.5 text-navy-600" />
                            <span class="flex-1 text-[10.5px]"><span class="font-500">Certificate of grades</span> <span class="text-mist">· PDF, JPG · up to 5 MB</span></span>
                            <span class="flex items-center gap-1 text-[10px] text-navy-700"><x-ui.icon name="check-circle" class="size-3" /> grades.pdf</span>
                        </div>
                        <div class="flex items-center gap-2 text-[10.5px] text-slate">
                            <span class="flex size-3 items-center justify-center rounded-[3px] bg-navy-600 text-white"><x-ui.icon name="check" class="size-2" /></span>
                            I agree to have my name in the published results <x-help.marker n="4" />
                        </div>
                    </div>
                </x-help.mock.card>
                <div class="flex justify-end gap-1.5"><x-help.mock.button>Review application <x-ui.icon name="arrow-right" class="size-3" /></x-help.mock.button><x-help.marker n="5" /></div>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1"><strong>Your identity</strong> comes from your profile and can’t be edited here. If something is wrong, use <strong>Update your profile in Settings</strong>, then come back.</x-help.step>
            <x-help.step n="2"><strong>Academic standing</strong>: your college, year level and family monthly income. Academic scholarships also ask for your <strong>GWA</strong>.</x-help.step>
            <x-help.step n="3"><strong>Program requirements</strong>: the fields and documents this scholarship asks for. Each upload shows the file types and maximum size it accepts. Files that are too big or the wrong type are refused right away, so you can fix them before submitting.</x-help.step>
            <x-help.step n="4">Choose whether your name may appear in the <strong>published results</strong> if you are awarded. If you say no, you are counted but not named.</x-help.step>
            <x-help.step n="5">Select <strong>Review application</strong>, check everything (use <strong>Back to edit</strong> to change anything), then select <strong>Submit application</strong>. You go straight to your new application’s page.</x-help.step>
        </x-help.steps>

        <x-help.note title="“Based on what you’ve entered, you may not qualify”">
            <p>This warning lists the rules your answers don’t seem to meet. You can still submit; OSDW confirms your eligibility during screening.</p>
        </x-help.note>
        <x-help.note tone="tip" title="Applying to more than one scholarship">
            <p>You may apply to several scholarships at once. The Apply page reminds you of the applications you already have.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="st-rules" title="When an application can’t be submitted">
        <p>Most rules are checked by OSDW after you submit. A few are checked right away, and the form tells you why it stopped:</p>
        <x-help.definitions>
            <x-help.definition term="Required GWA">An academic scholarship can require a minimum GWA. If yours is below it, the GWA field shows the rule and the application can’t be submitted.</x-help.definition>
            <x-help.definition term="Family income limit">Some scholarships have a maximum family monthly income. An application above it can’t be submitted.</x-help.definition>
            <x-help.definition term="One national scholarship">If you already hold a national scholarship (from a National Government or National Non-Government provider), you can’t apply to another national one. The scholarship page shows the reason instead of Apply now. You can still apply to local, in-house, academic and non-academic scholarships.</x-help.definition>
            <x-help.definition term="Already applied">If you already applied to the offering, Apply takes you to that application instead.</x-help.definition>
            <x-help.definition term="Registration closed">An offering stops taking applications at its deadline, or as soon as it reaches its maximum number of applications.</x-help.definition>
        </x-help.definitions>
        <x-help.note tone="warning" title="Two national scholarships at the same time">
            <p>You may apply to two national scholarships at once. Once one of them is awarded, the other can’t be awarded, and its application page says so.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="st-track" title="My applications">
        <p><strong>My applications</strong> lists every application and renewal you have, newest first.</p>

        <x-help.screen url="/my/applications" caption="My applications, filtered by status group." active="My applications">
            <p class="font-display text-[15px] font-600">My applications</p>
            <div class="mt-2.5 flex items-center gap-1.5">
                <div class="flex flex-1 rounded-[6px] bg-surface p-0.5 sm:max-w-[360px]">
                    @foreach ([['All', 3, true], ['In progress', 1, false], ['Awarded', 1, false], ['Not approved', 1, false]] as [$label, $count, $on])
                        <span @class(['flex flex-1 items-center justify-center gap-1 rounded-[5px] px-1.5 py-1 text-[10px] font-500', 'bg-paper text-ink shadow-sm' => $on, 'text-slate' => ! $on])>{{ $label }} <span class="font-mono text-[9px] text-mist">{{ $count }}</span></span>
                    @endforeach
                </div>
                <x-help.marker n="1" />
            </div>
            <x-help.mock.card class="mt-2.5">
                @foreach ([['CHED Merit Scholarship', 'Batch 1 · 1st sem 2026–2027', 'amber', 'Screening'], ['DOST-SEI Merit', 'Renewal · 2nd sem 2025–2026', 'navy', 'Renewed'], ['Provincial Educational Assistance', 'Batch 2 · 1st sem 2025–2026', 'crimson', 'Denied']] as [$name, $meta, $tone, $status])
                    <div class="flex items-center gap-3 border-b border-line px-3 py-2 last:border-0">
                        <div class="min-w-0 flex-1"><p class="truncate text-[11px] font-500">{{ $name }}</p><p class="truncate text-[9.5px] text-mist">{{ $meta }}</p></div>
                        <x-help.mock.stamp :tone="$tone">{{ $status }}</x-help.mock.stamp>
                        <x-ui.icon name="chevron-right" class="size-3 text-mist" />
                        @if ($loop->first)<x-help.marker n="2" />@endif
                    </div>
                @endforeach
            </x-help.mock.card>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Filter by <strong>All</strong>, <strong>In progress</strong> (submitted, under review, shortlisted, waitlisted), <strong>Awarded</strong> (awarded or renewed) or <strong>Not approved</strong>.</x-help.step>
            <x-help.step n="2">Select an application to open its page.</x-help.step>
        </x-help.steps>
    </x-help.topic>

    <x-help.topic id="st-application" title="Your application page">
        <p>Everything about one application is on its page. It updates as OSDW works on it.</p>

        <x-help.screen url="/my/applications/48" caption="An application page: the status headline, what to do next, the timeline, and what you submitted." active="My applications">
            <p class="text-[10px] text-slate">← My applications</p>
            <p class="mt-1 font-display text-[14px] font-600">CHED Merit Scholarship · Batch 1</p>
            <div class="mt-2.5 grid grid-cols-1 gap-2.5 sm:grid-cols-[1fr_170px]">
                <div class="space-y-2.5">
                    <div class="flex items-start gap-2.5 rounded-[10px] border border-line bg-surface p-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-navy-600 text-white"><x-ui.icon name="shortlist" class="size-3.5" /></span>
                        <div class="flex-1"><p class="font-display text-[12px] font-600">You are on the shortlist</p><p class="text-[10px] text-slate">Complete the requirements below to be considered for the award.</p></div>
                        <x-help.marker n="1" />
                    </div>
                    <x-help.mock.card title="Additional requirements" icon="clipboard-check">
                        <x-slot:meta><x-help.marker n="2" /></x-slot:meta>
                        @foreach ([['Notarized scholarship contract', 'To submit', 'amber'], ['Barangay certificate', 'Complied', 'navy']] as [$req, $state, $tone])
                            <div class="flex items-center gap-2 border-b border-line px-3 py-2 last:border-0">
                                <span class="flex-1 text-[10.5px] font-500">{{ $req }}</span>
                                <x-help.mock.stamp :tone="$tone">{{ $state }}</x-help.mock.stamp>
                            </div>
                        @endforeach
                    </x-help.mock.card>
                    <x-help.mock.card title="Interview" icon="calendar">
                        <div class="flex flex-wrap gap-x-4 gap-y-1 p-3 text-[10px] text-slate">
                            <span class="flex items-center gap-1"><x-ui.icon name="calendar" class="size-3" /> Nov 4, 2026 · 9:00 AM</span>
                            <span class="flex items-center gap-1"><x-ui.icon name="map-pin" class="size-3" /> OSDW Conference Room</span>
                        </div>
                    </x-help.mock.card>
                </div>
                <x-help.mock.card title="Status" icon="clock">
                    <x-slot:meta><x-help.marker n="3" /></x-slot:meta>
                    <div class="space-y-2 p-3 text-[10px]">
                        @foreach ([['Submitted', 'Oct 2'], ['Under review', 'Oct 3'], ['Shortlisted', 'Oct 14']] as [$step, $date])
                            <div class="flex items-center gap-2"><span class="size-2 rounded-full bg-navy-600"></span><span class="flex-1 font-500">{{ $step }}</span><span class="font-mono text-[9px] text-mist">{{ $date }}</span></div>
                        @endforeach
                        <div class="flex items-center gap-2 text-mist"><span class="size-2 rounded-full border border-mist"></span>Awarded</div>
                    </div>
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">The <strong>headline</strong> says where your application stands and what happens next, e.g. “Your application is under review”.</x-help.step>
            <x-help.step n="2">Anything you need to do appears here: <a href="#st-requirements" class="font-500 text-navy-700 hover:underline">additional requirements</a>, the interview, or renewal requirements.</x-help.step>
            <x-help.step n="3">The <strong>status timeline</strong> lists every step with its date, including any remarks from OSDW.</x-help.step>
        </x-help.steps>

        <p class="mt-4">Further down: <strong>screening notes</strong> (if you were found ineligible, the specific reason), the form and documents you submitted (read-only), and the scholarship’s announcements.</p>
    </x-help.topic>

    <x-help.topic id="st-requirements" title="Additional requirements and the interview">
        <p>While you apply, you only fill in the application form. Later stages appear on your application page when OSDW opens them, and you get a notification.</p>

        <h4>Shortlist requirements</h4>
        <p>When you are shortlisted and OSDW opens the shortlist requirements, each one appears with its instructions and how to hand it in:</p>
        <ul>
            <li><strong>Upload</strong>: choose the file right on the page.</li>
            <li><strong>Physical copy</strong>: bring the original to the OSDW office. Staff mark it received.</li>
            <li><strong>Both</strong>: upload it and bring the original.</li>
        </ul>
        <p>If a requirement has a <strong>blank form</strong> (e.g. a contract), select <strong>Download blank form</strong>, fill it in, then hand it in. Every shortlist requirement must be complied with before you can be awarded.</p>

        <x-help.definitions>
            <x-help.definition><x-slot:term><x-help.mock.stamp tone="amber" class="!text-[10.5px]">To submit</x-help.mock.stamp></x-slot:term> Not handed in yet.</x-help.definition>
            <x-help.definition><x-slot:term><x-help.mock.stamp tone="azure" class="!text-[10.5px]">Under review</x-help.mock.stamp></x-slot:term> Uploaded. OSDW is checking it.</x-help.definition>
            <x-help.definition><x-slot:term><x-help.mock.stamp tone="crimson" class="!text-[10.5px]">Returned</x-help.mock.stamp></x-slot:term> OSDW sent it back. Read the remarks, fix it, and upload it again.</x-help.definition>
            <x-help.definition><x-slot:term><x-help.mock.stamp class="!text-[10.5px]">Complied</x-help.mock.stamp></x-slot:term> Accepted. Nothing more to do.</x-help.definition>
        </x-help.definitions>

        <h4>Interview</h4>
        <p>If the scholarship has an interview, every shortlisted applicant sees the same date, time, venue and instructions on their application page. If OSDW moves it, you are notified.</p>
    </x-help.topic>

    <x-help.topic id="st-award" title="When you are awarded">
        <p>When OSDW awards you, your status becomes <strong>Awarded</strong>, and you get a notification and an email. A recorded award is final; there is nothing to accept.</p>

        <x-help.screen url="/my/applications/48" caption="An awarded application with disbursement requirements to hand in." active="My applications">
            <div class="flex items-start gap-2.5 rounded-[10px] border border-line bg-surface p-3">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-gold-600 text-white"><x-ui.icon name="award" class="size-3.5" /></span>
                <div class="flex-1"><p class="font-display text-[12px] font-600">You have been awarded</p><p class="text-[10px] text-slate">Complete the requirement below so your award can be released.</p></div>
                <x-help.mock.stamp>Awarded</x-help.mock.stamp>
            </div>
            <x-help.mock.card title="Disbursement requirements" icon="clipboard-check" class="mt-2.5">
                <div class="flex flex-wrap items-center gap-2 px-3 py-2">
                    <span class="flex-1 text-[10.5px] font-500">Signed acknowledgment receipt</span>
                    <x-help.mock.button variant="secondary" icon="download">Download blank form</x-help.mock.button>
                    <x-help.mock.button icon="upload">Upload file</x-help.mock.button>
                </div>
            </x-help.mock.card>
        </x-help.screen>

        <ul>
            <li><strong>Disbursement requirements</strong>: once every slot is filled, OSDW may ask awardees for documents needed to release the award. Each upload is submitted once; you can only upload again if OSDW returns it.</li>
            <li><strong>Disbursed</strong>: when OSDW records your award as released, your status reads Disbursed and you are notified.</li>
            <li><strong>Published results</strong>: when OSDW publishes the results, any instructions for awardees appear on your application page.</li>
        </ul>
    </x-help.topic>

    <x-help.topic id="st-renewal" title="Renewing your scholarship">
        <p>Scholars renew every term. When OSDW opens renewals, a <strong>renewal</strong> appears in My applications, and you are notified and emailed.</p>

        <x-help.screen url="/my/applications/77" caption="A renewal: enter your year level once, then hand in each renewal requirement." active="My applications">
            <div class="flex items-start gap-2.5 rounded-[10px] border border-line bg-surface p-3">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-600 text-white"><x-ui.icon name="clock" class="size-3.5" /></span>
                <div class="flex-1"><p class="font-display text-[12px] font-600">Hand in your requirements to renew your scholarship</p><p class="text-[10px] text-slate">Submit by Dec 5, 2026</p></div>
            </div>
            <div class="mt-2.5 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <x-help.mock.card title="Year level" icon="graduation">
                    <x-slot:meta><x-help.marker n="1" /></x-slot:meta>
                    <div class="flex items-end gap-1.5 p-3">
                        <x-help.mock.field label="Year level" value="3rd year" select class="flex-1" />
                        <x-help.mock.button>Submit year level</x-help.mock.button>
                    </div>
                    <p class="px-3 pb-2.5 text-[9.5px] text-mist">You can submit this once.</p>
                </x-help.mock.card>
                <x-help.mock.card title="Renewal requirements" icon="clipboard-check">
                    <x-slot:meta><x-help.marker n="2" /></x-slot:meta>
                    @foreach ([['Certificate of grades', 'Under review', 'azure'], ['Certificate of enrollment', 'To submit', 'amber']] as [$req, $state, $tone])
                        <div class="flex items-center gap-2 border-b border-line px-3 py-2 last:border-0">
                            <span class="flex-1 text-[10.5px] font-500">{{ $req }}</span>
                            <x-help.mock.stamp :tone="$tone">{{ $state }}</x-help.mock.stamp>
                        </div>
                    @endforeach
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Pick your <strong>year level this term</strong> and select <strong>Submit year level</strong>. You can submit it once only, so check it first.</x-help.step>
            <x-help.step n="2">Hand in each <strong>renewal requirement</strong> before the due date. Uploads are accepted only within the submission window. Each upload is submitted once; if OSDW returns it, read the remarks and upload a corrected file.</x-help.step>
            <x-help.step n="3">When everything is in, the page reads “Your renewal is under review”. OSDW then renews it, or explains why it was not approved.</x-help.step>
        </x-help.steps>
    </x-help.topic>

    <x-help.topic id="st-notifications" title="Notifications">
        <p>The <strong>bell</strong> in the header shows how many notifications you haven’t read. Select it for the latest ones; select one to open what it is about. <strong>Mark all as read</strong> clears the count, and <strong>View all notifications</strong> opens the full list.</p>

        <x-help.screen url="/my/applications" caption="The notification bell and its list." active="My applications">
            <div class="flex justify-end">
                <x-help.mock.card class="w-full max-w-[300px] shadow-sm">
                    <div class="flex items-center justify-between border-b border-line px-3 py-2">
                        <span class="font-display text-[11.5px] font-600">Notifications</span>
                        <span class="text-[10px] font-500 text-navy-700">Mark all as read</span>
                    </div>
                    @foreach ([['shortlist', 'You are on the shortlist', 'CHED Merit Scholarship · 2h ago', true], ['calendar', 'Interview scheduled', 'Nov 4, 9:00 AM · OSDW Conference Room', true], ['megaphone', 'Now open: DOST-SEI Merit', 'Apply until Nov 30 · yesterday', false]] as [$icon, $title, $meta, $unread])
                        <div @class(['flex gap-2 border-b border-line px-3 py-2 last:border-0', 'bg-navy-50/60' => $unread])>
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-700"><x-ui.icon :name="$icon" class="size-3" /></span>
                            <div class="min-w-0 flex-1"><p class="truncate text-[10.5px] font-500">{{ $title }}</p><p class="truncate text-[9.5px] text-mist">{{ $meta }}</p></div>
                            @if ($unread)<span class="mt-1 size-1.5 rounded-full bg-navy-600"></span>@endif
                        </div>
                    @endforeach
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <p>You are notified when:</p>
        <ul>
            <li>your application or renewal changes status, e.g. shortlisted, awarded, renewed, waitlisted or not approved;</li>
            <li>OSDW opens shortlist or disbursement requirements for you;</li>
            <li>the interview is scheduled or moved;</li>
            <li>OSDW posts an announcement for a scholarship you applied to, or a general announcement;</li>
            <li>the results of a scholarship you applied to are published;</li>
            <li>your award is recorded as disbursed;</li>
            <li>a scholarship opens, or closes, for applications.</li>
        </ul>
        <p>Award notices, renewal notices and reminders about incomplete applications are also emailed to you.</p>
    </x-help.topic>

    <x-help.topic id="st-results" title="Published results">
        <p>Select <strong>Results</strong> in the header. It lists every scholarship whose awardees OSDW has published. Open one to see OSDW’s instructions for awardees, then the awardees, with a search by name.</p>
        <x-help.note>
            <p>Only awardees who agreed to publication are named. The rest are counted, e.g. “3 more awardees chose not to have their names published”.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="st-settings" title="Settings and passwords">
        <h4>Settings</h4>
        <p>Select your initials, then <strong>Settings</strong>. You can edit your name, sex, birthdate, mobile number and address, and change your password. If you signed up with Google, you can set a password here too. Your Student ID and email can’t be changed; contact OSDW if they are wrong.</p>

        <h4>Forgot your password</h4>
        <x-help.steps>
            <x-help.step n="1">On the Log in page, select <strong>Forgot password?</strong></x-help.step>
            <x-help.step n="2">Enter your email and type the 6-digit code sent to it.</x-help.step>
            <x-help.step n="3">Choose a new password, then log in with it.</x-help.step>
        </x-help.steps>
    </x-help.topic>

</x-help.chapter>
