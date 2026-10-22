<x-help.chapter :id="$chapter['id']" :number="$number" :title="$chapter['title']" :icon="$chapter['icon']">

    <x-help.topic id="ad-overview" title="Setting up a scholarship, start to finish">
        <p>Administrators can do everything in <a href="#staff" class="font-500 text-navy-700 hover:underline">For OSDW staff</a>, plus the setup below. To take a new scholarship from nothing to awarded scholars:</p>
        <x-help.steps>
            <x-help.step n="1">Make sure the <strong>campus</strong>, <strong>colleges</strong>, <strong>academic term</strong> and the <strong>provider</strong> exist (<a href="#ad-reference" class="font-500 text-navy-700 hover:underline">Reference data</a>).</x-help.step>
            <x-help.step n="2">Add the <strong>scholarship program</strong> (<a href="#ad-programs" class="font-500 text-navy-700 hover:underline">Scholarship programs</a>).</x-help.step>
            <x-help.step n="3"><strong>Add an offering</strong> for the term: slots, dates, amount (<a href="#ad-offerings" class="font-500 text-navy-700 hover:underline">Opening an offering</a>).</x-help.step>
            <x-help.step n="4">Set up its <strong>Requirements page</strong>: the application form, required GWA, additional requirements and interview (<a href="#ad-requirements" class="font-500 text-navy-700 hover:underline">The Requirements page</a>).</x-help.step>
            <x-help.step n="5">On the opening date students can apply. Staff screen, shortlist and award. Next term, <a href="#ad-renewals" class="font-500 text-navy-700 hover:underline">add a renewal offering and open renewals</a>.</x-help.step>
        </x-help.steps>
    </x-help.topic>

    <x-help.topic id="ad-reference" title="Reference data">
        <p>Select <strong>Settings</strong> at the bottom of the sidebar. The Application settings page groups everything you maintain.</p>

        <x-help.screen url="/admin/settings" caption="Application settings: reference data on the left, system pages on the right." variant="staff" active="Settings" :admin="true">
            <p class="font-display text-[15px] font-600">Application settings</p>
            <div class="mt-2.5 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                @foreach ([['Reference data', 'layers', [['map-pin', 'Campus', 'Campus name printed on reports'], ['building', 'Colleges', 'Colleges that classify programs and applications'], ['graduation', 'Programs', 'Academic programs students enroll in'], ['users', 'Providers', 'Scholarship sponsors and their funding source'], ['calendar', 'Academic terms', 'Open, set current or close a term']]], ['System', 'sliders', [['user', 'Users', 'Staff accounts, passwords and access'], ['clipboard-check', 'Audit log', 'Who changed what on scholarship decisions'], ['mail', 'Email log', 'Notices the system has sent'], ['trash', 'Deleted items', 'Restore deleted programs and offerings'], ['download', 'Database backup', 'Download a full SQL backup']]]] as [$title, $icon, $items])
                    <x-help.mock.card :title="$title" :icon="$icon">
                        @foreach ($items as [$itemIcon, $label, $desc])
                            <div class="flex items-center gap-2 border-b border-line px-3 py-1.5 last:border-0">
                                <span class="flex size-5 items-center justify-center rounded-[5px] border border-line bg-paper text-slate"><x-ui.icon :name="$itemIcon" class="size-3" /></span>
                                <div class="min-w-0 flex-1"><p class="truncate text-[10.5px] font-500">{{ $label }}</p><p class="truncate text-[9px] text-mist">{{ $desc }}</p></div>
                                <x-ui.icon name="chevron-right" class="size-3 text-mist" />
                            </div>
                        @endforeach
                    </x-help.mock.card>
                @endforeach
            </div>
        </x-help.screen>

        <x-help.definitions>
            <x-help.definition term="Campus">The campus name printed on reports. Set it up before opening an offering.</x-help.definition>
            <x-help.definition term="Colleges">The colleges applicants choose and offerings can be limited to.</x-help.definition>
            <x-help.definition term="Programs">The academic programs (courses) students enroll in.</x-help.definition>
            <x-help.definition term="Providers">Sponsors, with contact details and a <strong>funding source</strong>. The funding source decides whether the provider’s scholarships count as national for the one-national rule. Also in the sidebar as <strong>Providers</strong>.</x-help.definition>
            <x-help.definition term="Academic terms">School year and semester with start and end dates. <strong>Set as current</strong> marks the term the dashboard reports on.</x-help.definition>
        </x-help.definitions>
    </x-help.topic>

    <x-help.topic id="ad-programs" title="Scholarship programs">
        <p>Select <strong>Scholarship Programs</strong> in the sidebar, then <strong>Add scholarship program</strong>. A program is only its name, provider and description; everything that changes from term to term belongs to its offerings.</p>
        <ul>
            <li>Every staff and administrator account is notified of a new program.</li>
            <li>Select a program to see its offerings. <strong>Deactivate</strong> hides it without deleting it; you can activate it again anytime.</li>
            <li><strong>Edit standard requirements</strong> opens the shared starting point for offerings (see <a href="#ad-standard" class="font-500 text-navy-700 hover:underline">Standard requirements</a>).</li>
        </ul>
    </x-help.topic>

    <x-help.topic id="ad-offerings" title="Opening an offering">
        <p>On a program’s page, select <strong>Add offering</strong>.</p>

        <x-help.screen url="/admin/scholarship-programs/4/offerings" caption="The offering form." variant="staff" active="Scholarship Programs" :admin="true">
            <div class="mx-auto max-w-[420px]">
                <x-help.mock.card title="Add offering">
                    <div class="grid grid-cols-2 gap-2 p-3">
                        <x-help.mock.field label="Offering name" value="Batch 1" />
                        <x-help.mock.field label="Academic term" value="2026–2027 · 1st sem" select />
                        <x-help.mock.field label="Type of scholarship" value="Academic" select />
                        <x-help.mock.field label="Award amount (₱)" value="5,000.00" mono />
                        <x-help.mock.field label="Opens" value="2026-10-01 08:00" mono />
                        <x-help.mock.field label="Closes" value="2026-10-31 17:00" mono />
                        <x-help.mock.field label="Total slots" value="10" mono />
                        <x-help.mock.field label="Maximum applications" value="" placeholder>Leave blank for no cap</x-help.mock.field>
                        <x-help.mock.field label="Maximum family monthly income" value="25,000" mono class="col-span-2" />
                        <x-help.mock.field label="College scope" value="All colleges" select />
                        <x-help.mock.field label="Allocation mode" value="Open competition" select />
                        <div class="col-span-2 flex justify-end gap-1.5"><x-help.mock.button variant="secondary">Cancel</x-help.mock.button><x-help.mock.button>Add offering</x-help.mock.button></div>
                    </div>
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <x-help.definitions>
            <x-help.definition term="Offering name">What staff and students see, e.g. “Batch 1”.</x-help.definition>
            <x-help.definition term="Type of scholarship">Academic offerings also ask applicants for their GWA, and can require a minimum.</x-help.definition>
            <x-help.definition term="Opens / Closes">The application window. Students only see the offering while it is open. Everyone is notified when it opens and when it closes.</x-help.definition>
            <x-help.definition term="Total slots">How many applicants can be awarded.</x-help.definition>
            <x-help.definition term="Maximum applications">Registration closes as soon as this many have applied. Leave blank for no cap.</x-help.definition>
            <x-help.definition term="Maximum family monthly income">Applications above it can’t be submitted. Students never see the amount. Leave blank for no cap.</x-help.definition>
            <x-help.definition term="College scope / Allocation">All colleges, or only selected ones; and open competition, a quota per college, or proportional slots.</x-help.definition>
            <x-help.definition term="Award amount">The cash amount per grantee. It shows only on staff pages, reports and exports, never on public pages.</x-help.definition>
        </x-help.definitions>
        <x-help.note tone="tip">
            <p>Each offering is independent. Editing one never changes another, even in the same program.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="ad-requirements" title="The Requirements page">
        <p>Open an offering and select <strong>Requirements</strong>. The page has three sections.</p>

        <x-help.screen url="/admin/offerings/12/configure" caption="The Requirements page: form fields on the left, the applicant’s view on the right." variant="staff" active="Scholarship Programs" :admin="true">
            <div class="flex flex-wrap items-center gap-1.5">
                <p class="mr-auto font-display text-[14px] font-600">Requirements · Batch 1</p>
                <div class="flex rounded-[6px] bg-surface p-0.5 text-[10px] font-500">
                    <span class="px-2 py-0.5 text-slate">Standard requirements</span>
                    <span class="rounded-[5px] bg-paper px-2 py-0.5 shadow-sm">Set manually</span>
                </div>
                <x-help.marker n="1" />
            </div>
            <div class="mt-2 flex gap-1 border-b border-line text-[10px] font-500">
                <span class="border-b-2 border-navy-600 px-2 pb-1 text-navy-700">Application form</span>
                <span class="px-2 pb-1 text-slate">Additional requirements</span>
                <span class="px-2 pb-1 text-slate">Interview</span>
                <x-help.marker n="2" class="mb-1" />
            </div>
            <div class="mt-2.5 grid grid-cols-1 gap-2.5 sm:grid-cols-[1fr_150px]">
                <div class="space-y-1.5">
                    @foreach ([['upload', 'Certificate of grades', 'File · required · PDF, JPG · 5 MB'], ['list', 'Are you a 4Ps beneficiary?', 'Radio · required'], ['type', 'Name of high school', 'Text · optional']] as [$icon, $label, $meta])
                        <x-help.mock.card>
                            <div class="flex items-center gap-2 px-3 py-2">
                                <span class="flex size-5 items-center justify-center rounded-[5px] bg-navy-50 text-navy-700"><x-ui.icon :name="$icon" class="size-3" /></span>
                                <div class="min-w-0 flex-1"><p class="truncate text-[10.5px] font-500">{{ $label }}</p><p class="truncate text-[9px] text-mist">{{ $meta }}</p></div>
                                <x-ui.icon name="chevron-down" class="size-3 text-mist" />
                            </div>
                        </x-help.mock.card>
                    @endforeach
                    <x-help.mock.button variant="secondary" icon="plus">Add field</x-help.mock.button>
                    <x-help.marker n="3" />
                </div>
                <x-help.mock.card title="Applicant view" icon="eye">
                    <div class="space-y-1.5 p-2.5">
                        <x-help.mock.field label="Certificate of grades" value="Choose file" placeholder />
                        <x-help.mock.field label="Required GWA" value="1.75" mono />
                        <x-help.marker n="4" />
                    </div>
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1"><strong>Requirements source</strong>: <strong>Set manually</strong> (default) or <strong>Standard requirements</strong>, which copies the standard application form and disbursement requirements in and makes them read-only. You can choose Standard only before the first application.</x-help.step>
            <x-help.step n="2">The three sections: <strong>Application form</strong>, <strong>Additional requirements</strong> and <strong>Interview</strong>.</x-help.step>
            <x-help.step n="3"><strong>Application form</strong>: add fields of any type (text, paragraph, number, date, dropdown, checkbox, radio, file). Each card expands to edit its label, whether it’s required, and for files the formats, maximum size, and whether a physical copy is needed. College, year level and family income are always asked.</x-help.step>
            <x-help.step n="4">The <strong>applicant view</strong> shows the form exactly as students will see it. On an academic offering, set the <strong>required GWA</strong> here; applications below it can’t be submitted.</x-help.step>
        </x-help.steps>

        <h4>Additional requirements</h4>
        <p>Pick the stage first:</p>
        <ul>
            <li><strong>While shortlisted</strong>: must be complied with before the applicant can be awarded. Staff show them with <em>Open shortlist requirements</em>.</li>
            <li><strong>After awarding</strong> (disbursement): asked of awardees for the award release. They never block the award.</li>
        </ul>
        <p>Then the label, instructions, and how it is handed in: upload, physical copy, or both (with formats and maximum size for uploads). You can attach a <strong>blank form</strong> (PDF or Word), such as a contract, for applicants to download and fill in. Reorder, edit or remove them per stage.</p>

        <h4>Interview</h4>
        <p>Tick <strong>Shortlisted applicants must attend an interview</strong>, then set one date and time, a venue and instructions, and select <strong>Save interview details</strong>. The preview shows what shortlisted applicants will see. Shortlisted applicants and all staff are notified when it is set or moved.</p>
    </x-help.topic>

    <x-help.topic id="ad-standard" title="Standard requirements">
        <p>On Scholarship Programs, <strong>Edit standard requirements</strong> opens one shared application form and one set of disbursement requirements. They are a starting point for offerings that choose <strong>Standard requirements</strong>.</p>
        <ul>
            <li>Changes are copied into every Standard offering that has no applications yet. Once an offering receives its first application, its copy stays as it was.</li>
            <li>There are no standard shortlist requirements. The required GWA, shortlist requirements and interview are always set per offering.</li>
            <li>Switching an offering back to <strong>Set manually</strong> keeps the copied items and makes them editable.</li>
        </ul>
    </x-help.topic>

    <x-help.topic id="ad-renewals" title="Renewal offerings and opening renewals">
        <p>Scholars renew each term through a <strong>renewal offering</strong> that continues an earlier offering.</p>
        <x-help.steps>
            <x-help.step n="1">On an offering’s page, select <strong>Add renewal offering</strong>. It is linked to the latest offering in the group and keeps its type of scholarship.</x-help.step>
            <x-help.step n="2">Set the <strong>Submission starts</strong> and <strong>Submission due</strong> dates. Scholars can upload renewal requirements only within this window.</x-help.step>
            <x-help.step n="3">Optional: tick <strong>Open to new applicants</strong> to also take new applicants this term. It then gets slots for them (suggested as the number of scholars in their last term), an application form, shortlist requirements and an interview, like a first offering.</x-help.step>
            <x-help.step n="4">On its Requirements page, add the <strong>renewal requirements</strong>: label, instructions, upload / physical copy / both, and an optional blank form. Every one must be complied with before staff can renew.</x-help.step>
            <x-help.step n="5">Select <strong>Open renewals</strong>. Every scholar awarded in the renewed offering gets a renewal under review and an email. Running it again skips scholars who already have one.</x-help.step>
        </x-help.steps>
        <x-help.note>
            <p>There are no renewal slots and no automatic graduation check. Every scholar still enrolled renews, and staff deny a renewal (e.g. graduated) to end it.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="ad-delete" title="Editing, deleting and restoring">
        <ul>
            <li>Each offering tab has a <strong>⋮</strong> menu with <strong>Edit offering</strong> and <strong>Delete offering</strong>, on the offering page and on the staff review pages.</li>
            <li>An offering can be deleted only while it holds no applications or renewals and no renewal offering continues it. A scholarship program can be deleted only while none of its offerings holds applications; its offerings go with it.</li>
            <li><strong>Settings → Deleted items</strong> lists what was deleted, with the days left. <strong>Restore</strong> brings it back (a program brings back its offerings). After <strong>60 days</strong> it is removed for good.</li>
        </ul>
        <x-help.note tone="rule">
            <p>Records of real students can never be deleted.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="ad-users" title="Staff accounts">
        <p>Select <strong>Users</strong> in the sidebar.</p>

        <x-help.screen url="/admin/users" caption="Staff users, with the Setup pending stamp and row actions." variant="staff" active="Users" :admin="true">
            <div class="flex items-center gap-1.5">
                <p class="mr-auto font-display text-[15px] font-600">Staff users</p>
                <x-help.mock.button icon="plus">Add user</x-help.mock.button>
                <x-help.marker n="1" />
            </div>
            <x-help.mock.card class="mt-2.5">
                @foreach ([['Marites Soriano', 'marites.soriano@csu.edu.ph', 'Active', 'navy'], ['Ramon Aquino', 'ramon.aquino@csu.edu.ph', 'Setup pending', 'amber']] as [$name, $email, $status, $tone])
                    <div class="flex items-center gap-2 border-b border-line px-3 py-2 last:border-0">
                        <div class="min-w-0 flex-1"><p class="truncate text-[10.5px] font-500">{{ $name }}</p><p class="truncate text-[9.5px] text-mist">{{ $email }}</p></div>
                        <x-help.mock.stamp :tone="$tone">{{ $status }}</x-help.mock.stamp>
                        <span class="flex gap-1 text-slate"><x-ui.icon name="pencil" class="size-3" /><x-ui.icon name="lock" class="size-3" /><x-ui.icon name="power" class="size-3" /></span>
                        @if ($loop->last)<x-help.marker n="2" />@endif
                    </div>
                @endforeach
            </x-help.mock.card>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1"><strong>Add user</strong>: enter the name and email. The system generates a temporary password and shows it to you <strong>once</strong>. Copy it and hand it over securely.</x-help.step>
            <x-help.step n="2"><strong>Setup pending</strong> means they haven’t finished their <a href="#sf-setup" class="font-500 text-navy-700 hover:underline">first log-in</a>. Row actions: edit name and email, <strong>Reset password</strong> (a new temporary password, shown once, changed at their next log-in), and deactivate or activate.</x-help.step>
        </x-help.steps>
    </x-help.topic>

    <x-help.topic id="ad-maintenance" title="Audit log, email log and backups">
        <x-help.definitions>
            <x-help.definition term="Audit log">Every create, update, approval and override, with the user, time and IP address. Filter by user, action, record type and dates. In the sidebar as <strong>Audit Log</strong>.</x-help.definition>
            <x-help.definition term="Email log">Every notice the system has emailed and whether it was sent.</x-help.definition>
            <x-help.definition term="System settings">The suggested maximum upload size and public page visibility.</x-help.definition>
            <x-help.definition term="Database backup"><strong>Download backup (.sql)</strong> saves a full copy of the database. Keep it somewhere safe; it contains student records.</x-help.definition>
        </x-help.definitions>
    </x-help.topic>

</x-help.chapter>
