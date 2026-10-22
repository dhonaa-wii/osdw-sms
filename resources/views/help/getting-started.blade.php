<x-help.chapter :id="$chapter['id']" :number="$number" :title="$chapter['title']" :icon="$chapter['icon']">

    <x-help.topic id="gs-about" title="About the system">
        <p>The <strong>OSDW Scholarship Management System</strong> is where the Office of Student Development and Welfare runs its scholarships from start to finish: students find and apply for scholarships, OSDW screens and shortlists applicants, awards are recorded, scholars renew each term, and the office prints the SUC-Levelling report.</p>
        <p>Everything happens on one website. It works on phones, tablets and computers, so you can apply and check your status from your phone.</p>

        <x-help.note title="What the system does, and what it doesn’t">
            <p>The system <strong>screens and records</strong>. It checks applications for completeness and eligibility, but OSDW staff make every decision: who is shortlisted, who is awarded, and who is renewed. It does not rank or score applicants, and it does not pay out awards; it records when an award was disbursed.</p>
        </x-help.note>

        <x-help.screen url="/" caption="The landing page. Open scholarships, announcements and published results are on the front page, and Help Center is in the top bar." variant="bare">
            <div class="-m-3 bg-surface sm:-m-5">
                <div class="flex h-9 items-center gap-3 border-b border-line px-4">
                    <span class="font-display text-[11.5px] font-600 text-navy-700">OSDW Scholarship System</span>
                    <span class="hidden items-center gap-1 rounded-full border border-line px-1.5 py-px text-[9px] text-slate sm:inline-flex"><span class="size-1 rounded-full bg-navy-600"></span>3 open now</span>
                    <span class="ml-auto flex items-center gap-3 text-[10px] font-500">
                        <span class="hidden sm:inline">Scholarship Programs</span>
                        <span class="inline-flex items-center gap-1"><x-ui.icon name="help" class="size-3" /> Help Center <x-help.marker n="1" class="ml-0.5" /></span>
                        <span class="hidden text-slate sm:inline">Log in</span>
                        <span class="rounded-[5px] bg-navy-600 px-2 py-0.5 text-white">Register</span>
                    </span>
                </div>
                <div class="grid grid-cols-1 items-center gap-5 px-5 py-7 sm:grid-cols-[1fr_130px]">
                    <div>
                        <p class="text-[9px] font-600 text-navy-700">Office of Student Development and Welfare</p>
                        <p class="mt-1 font-display text-[19px] font-700 leading-tight text-ink">A Web-Based Scholarship Management System</p>
                        <p class="mt-1.5 max-w-[340px] text-[10.5px] text-slate">Browse every scholarship currently accepting applications, apply with your student account, and follow each application from one page.</p>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <x-help.mock.button>Browse scholarships</x-help.mock.button>
                            <x-help.mock.button variant="secondary">View published results</x-help.mock.button>
                        </div>
                    </div>
                    <span class="mx-auto hidden size-[120px] items-center justify-center rounded-full border-4 border-navy-50 bg-navy-600 text-white sm:flex">
                        <x-ui.icon name="graduation" class="size-12" />
                    </span>
                </div>
                <div class="grid grid-cols-4 gap-3 border-t border-line px-5 py-3">
                    @foreach ([['3', 'Open scholarships'], ['9', 'Colleges served'], ['1', 'Campus'], ['12d', 'Until the nearest deadline']] as [$value, $label])
                        <div><p class="font-mono text-[14px] font-500 text-navy-700">{{ $value }}</p><p class="text-[9px] text-slate">{{ $label }}</p></div>
                    @endforeach
                </div>
            </div>
        </x-help.screen>
    </x-help.topic>

    <x-help.topic id="gs-roles" title="Who uses it">
        <p>There are three kinds of accounts. Everyone signs in on the same <strong>Log in</strong> page and lands on their own home page.</p>

        <x-help.definitions>
            <x-help.definition term="Student">
                Registers with their own email, sets up a profile with their Student ID, applies to open scholarships, hands in requirements, and follows each application. Lands on <strong>My applications</strong>.
            </x-help.definition>
            <x-help.definition term="OSDW staff">
                Reviews every applicant across all colleges: verifies documents, endorses or rejects, awards from the shortlist, verifies renewals, records disbursement, posts announcements and prints reports. Lands on the <strong>Dashboard</strong>.
            </x-help.definition>
            <x-help.definition term="Administrator">
                Can do everything staff can, and also sets up scholarship programs, offerings and their requirements, manages staff accounts, reference data and settings, and sees the audit log. Lands on the <strong>Dashboard</strong>.
            </x-help.definition>
        </x-help.definitions>

        <x-help.note tone="tip">
            <p>Browsing open scholarships and published results needs no account. You only need to sign in to apply.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="gs-signin" title="Signing in">
        <x-help.screen url="/login" caption="The Log in page, shared by students, staff and administrators." :student="false">
            <div class="mx-auto max-w-[300px]">
                <x-help.mock.card>
                    <div class="space-y-2.5 p-4">
                        <p class="text-center font-display text-[14px] font-600">Log in</p>
                        <div class="flex items-center justify-center gap-1.5 rounded-[6px] border border-line py-1.5 text-[10.5px] font-500">
                            <span class="font-display font-700 text-azure-600">G</span> Continue with Google <x-help.marker n="3" class="ml-1" />
                        </div>
                        <div class="flex items-center gap-2 text-[9px] text-mist"><span class="h-px flex-1 bg-line"></span>or<span class="h-px flex-1 bg-line"></span></div>
                        <div class="flex items-end gap-1.5"><x-help.mock.field label="Email address" value="juan.delacruz@gmail.com" class="flex-1" /><x-help.marker n="1" class="mb-1" /></div>
                        <x-help.mock.field label="Password" value="••••••••••" />
                        <div class="flex items-center justify-between text-[10px]">
                            <span class="flex items-center gap-1 text-slate"><span class="flex size-3 items-center justify-center rounded-[3px] bg-navy-600 text-white"><x-ui.icon name="check" class="size-2" /></span>Keep me signed in</span>
                            <span class="flex items-center gap-1 font-500 text-navy-700">Forgot password? <x-help.marker n="4" /></span>
                        </div>
                        <div class="flex items-center gap-1.5"><x-help.mock.button class="flex-1">Log in</x-help.mock.button><x-help.marker n="2" /></div>
                        <p class="text-center text-[10px] text-slate">New here? <span class="font-500 text-navy-700">Register</span></p>
                    </div>
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Enter the <strong>email address</strong> of your account and your password. The password is the one you set in this system, not your email’s password.</x-help.step>
            <x-help.step n="2">Select <strong>Log in</strong>. Tick <strong>Keep me signed in</strong> on your own device only.</x-help.step>
            <x-help.step n="3"><strong>Continue with Google</strong> signs you in with your Google account instead, when the office has turned it on. Staff can use it once they have connected Google to their account.</x-help.step>
            <x-help.step n="4">Forgot your password? Select <strong>Forgot password?</strong> and follow <a href="#st-settings" class="font-500 text-navy-700 hover:underline">Settings and passwords</a>.</x-help.step>
        </x-help.steps>
    </x-help.topic>

    <x-help.topic id="gs-layout" title="Finding your way around">
        <h4>Students</h4>
        <p>The header at the top of every page has <strong>Browse</strong>, <strong>My applications</strong> and <strong>Results</strong>, then the <strong>Help</strong> button, the notification bell, and your initials. Select your initials for Notifications, Settings and Log out. On a phone the links shrink to icons.</p>

        <h4>Staff and administrators</h4>
        <p>The sidebar on the left lists the main pages. Administrators see extra items: Scholarship Programs and Providers, and Audit Log, Users and Settings at the bottom. The top bar has <strong>Help</strong>, the notification bell and your account menu (Notifications, Profile, Log out). On a phone or tablet, open the sidebar with the menu button at the top left.</p>

        <x-help.screen url="/dashboard" caption="The staff layout: sidebar on the left, Help, the bell and the account menu in the top bar." variant="staff" active="Dashboard" :admin="true">
            <div class="flex items-center justify-between">
                <p class="font-display text-[15px] font-600">Dashboard</p>
                <span class="flex items-center gap-1 text-[10px] text-slate"><x-help.marker n="1" /> Help · bell · account menu</span>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ([['Received', '128'], ['Screened', '96'], ['Shortlisted', '41'], ['Awarded', '25']] as [$label, $value])
                    <x-help.mock.card><div class="p-2.5"><p class="text-[9.5px] text-slate">{{ $label }}</p><p class="font-mono text-[15px] font-500">{{ $value }}</p></div></x-help.mock.card>
                @endforeach
            </div>
        </x-help.screen>

        <h4>Messages and confirmations</h4>
        <p>Before anything that can’t be undone, such as awarding, publishing or opening requirements, the system asks you to confirm in a small window. Staff see the result in a message window. Students see a short message at the bottom of the screen that disappears on its own.</p>

        <h4>Documents</h4>
        <p>Uploaded files open inside the system in a viewer, so you never lose your place. On the review pages staff see each document in full, page by page.</p>
    </x-help.topic>

    <x-help.topic id="gs-statuses" title="Application statuses">
        <p>Each application shows a <strong>status stamp</strong>: a short label with a coloured rule on its left. Amber means waiting, navy means moving forward, crimson means not approved.</p>

        <x-help.definitions>
            <x-help.definition><x-slot:term><x-help.mock.stamp tone="amber" class="!text-[10.5px]">Submitted</x-help.mock.stamp></x-slot:term> The application is in. OSDW hasn’t started on it yet.</x-help.definition>
            <x-help.definition><x-slot:term><x-help.mock.stamp tone="amber" class="!text-[10.5px]">Screening</x-help.mock.stamp></x-slot:term> OSDW is checking the documents and eligibility. Students read this as “Your application is under review”.</x-help.definition>
            <x-help.definition><x-slot:term><x-help.mock.stamp class="!text-[10.5px]">Shortlisted</x-help.mock.stamp></x-slot:term> Endorsed by staff. The applicant may be asked for additional requirements and an interview before the award.</x-help.definition>
            <x-help.definition><x-slot:term><x-help.mock.stamp tone="amber" class="!text-[10.5px]">Waitlisted</x-help.mock.stamp></x-slot:term> On hold. The applicant is contacted if a slot opens up.</x-help.definition>
            <x-help.definition><x-slot:term><x-help.mock.stamp class="!text-[10.5px]">Awarded</x-help.mock.stamp></x-slot:term> The scholarship is recorded. A recorded award is final.</x-help.definition>
            <x-help.definition><x-slot:term><x-help.mock.stamp class="!text-[10.5px]">Disbursed</x-help.mock.stamp></x-slot:term> OSDW has recorded that the award was released.</x-help.definition>
            <x-help.definition><x-slot:term><x-help.mock.stamp tone="crimson" class="!text-[10.5px]">Denied</x-help.mock.stamp></x-slot:term> Not approved. The reason is shown on the application page.</x-help.definition>
        </x-help.definitions>

        <p class="mt-4">Renewals use their own words: <strong>Under review</strong> while the scholar hands in requirements and staff check them, <strong>Renewed</strong> once staff renew it, and <strong>Denied</strong> when the renewal is not approved.</p>
    </x-help.topic>

    <x-help.topic id="gs-terms" title="Key terms">
        <x-help.definitions>
            <x-help.definition term="Provider">The sponsor of a scholarship, such as CHED, DOST-SEI or a local government. Each provider has a funding source: national or local government, national or local non-government, in-house, academic or non-academic.</x-help.definition>
            <x-help.definition term="Scholarship program">A scholarship a provider offers, e.g. “CHED Merit Scholarship”. The program is just its name, provider and description.</x-help.definition>
            <x-help.definition term="Offering">One run of a program for a term, named by OSDW (e.g. “Batch 1”). It has its own slots, dates, amount, form and requirements. You apply to an offering.</x-help.definition>
            <x-help.definition term="Renewal offering">An offering that continues an earlier one for the next term. It takes that offering’s scholars, and can also take new applicants.</x-help.definition>
            <x-help.definition term="Application form">What a student fills in and uploads to apply. Staff check it during screening.</x-help.definition>
            <x-help.definition term="Shortlist requirements">Extra documents asked of shortlisted applicants (the “Additional requirements”). All must be complied with before the award.</x-help.definition>
            <x-help.definition term="Disbursement requirements">Documents asked of awardees for the award release. They never block the award itself.</x-help.definition>
            <x-help.definition term="Renewal requirements">What a scholar hands in each term to renew.</x-help.definition>
            <x-help.definition term="Endorse">Staff move an application from screening to the shortlist.</x-help.definition>
            <x-help.definition term="Award">Staff record the scholarship for a shortlisted applicant, within the offering’s slots.</x-help.definition>
            <x-help.definition term="Disburse">Staff record that an award was released. The system records it; it does not pay.</x-help.definition>
            <x-help.definition term="Complied">A requirement is complied when its upload is verified, its physical copy is received, or both, depending on what it asks for.</x-help.definition>
            <x-help.definition term="National scholarship">A scholarship whose provider is funded nationally (National Government or National Non-Government). A student may hold only one at a time.</x-help.definition>
        </x-help.definitions>
    </x-help.topic>

</x-help.chapter>
