<x-help.chapter :id="$chapter['id']" :number="$number" :title="$chapter['title']" :icon="$chapter['icon']">

    <x-help.topic id="sf-setup" title="Your first log-in">
        <p>The administrator creates your account and gives you a <strong>temporary password</strong>. The first time you log in, a setup page opens before anything else.</p>

        <x-help.screen url="/staff/account/setup" caption="First log-in: set your own password, then connect Google." variant="bare">
            <div class="mx-auto max-w-[340px] space-y-2.5">
                <x-help.mock.card title="Set your own password" icon="lock">
                    <x-slot:meta><x-help.marker n="1" /></x-slot:meta>
                    <div class="space-y-2 p-3">
                        <x-help.mock.field label="New password" value="••••••••••" />
                        <x-help.mock.field label="Confirm password" value="••••••••••" />
                        <div class="flex justify-end"><x-help.mock.button>Save password</x-help.mock.button></div>
                    </div>
                </x-help.mock.card>
                <x-help.mock.card title="Connect your Google account" icon="link">
                    <x-slot:meta><x-help.marker n="2" /></x-slot:meta>
                    <div class="p-3">
                        <div class="flex items-center justify-center gap-1.5 rounded-[6px] border border-line py-1.5 text-[10.5px] font-500">
                            <span class="font-display font-700 text-azure-600">G</span> Continue with Google
                        </div>
                    </div>
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Choose a new password, different from the temporary one, and select <strong>Save password</strong>.</x-help.step>
            <x-help.step n="2">If Google sign-in is turned on, connect your Google account. A Google account already used by someone else is refused.</x-help.step>
        </x-help.steps>
        <p class="mt-4">Until both are done, every staff page brings you back here. Administrators skip this page.</p>
    </x-help.topic>

    <x-help.topic id="sf-dashboard" title="Dashboard">
        <p>The Dashboard is your home page. It shows how the current term is going and what needs attention.</p>

        <x-help.screen url="/dashboard" caption="The Dashboard: this term’s numbers, pending tasks and open offerings." variant="staff" active="Dashboard">
            <div class="flex items-center gap-2">
                <p class="mr-auto font-display text-[15px] font-600">Dashboard</p>
                <span class="flex h-6 w-[150px] items-center gap-1 rounded-[6px] border border-line bg-surface px-2 text-[9.5px] text-mist"><x-ui.icon name="search" class="size-3" /> Search programs and providers</span>
                <x-help.marker n="1" />
            </div>
            <div class="mt-2.5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ([['Received', '128'], ['Screened', '96'], ['Shortlisted', '41'], ['Awarded', '25']] as [$label, $value])
                    <x-help.mock.card><div class="p-2.5"><p class="text-[9.5px] text-slate">{{ $label }}</p><p class="font-mono text-[15px] font-500">{{ $value }}</p></div></x-help.mock.card>
                @endforeach
            </div>
            <div class="mt-2.5 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_170px]">
                <x-help.mock.card title="Application activity" icon="chart-bar">
                    <div class="flex h-[70px] items-end gap-1 px-3 pb-2 pt-3">
                        @foreach ([30, 55, 40, 72, 60, 85, 48, 66, 52, 78, 90, 64, 70, 58] as $h)
                            <span class="flex-1 rounded-t-[2px] bg-navy-600/80" style="height: {{ $h }}%"></span>
                        @endforeach
                    </div>
                </x-help.mock.card>
                <x-help.mock.card title="Pending tasks" icon="clock">
                    <x-slot:meta><x-help.marker n="2" /></x-slot:meta>
                    @foreach ([['Applications to screen', '14'], ['Documents to verify', '22'], ['Application cap reached', '1']] as [$task, $count])
                        <div class="flex items-center justify-between border-b border-line px-3 py-1.5 text-[10px] last:border-0"><span>{{ $task }}</span><span class="font-mono text-amber-600">{{ $count }}</span></div>
                    @endforeach
                </x-help.mock.card>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1"><strong>Search programs and providers</strong> jumps straight to a scholarship’s review pages. Press <strong>Ctrl K</strong> to focus it.</x-help.step>
            <x-help.step n="2"><strong>Pending tasks</strong> counts unreviewed applications, unverified documents and offerings at their application cap, with shortcuts to the pages that clear them.</x-help.step>
        </x-help.steps>
        <p class="mt-4">Below are the application status breakdown, slots filled this term, open offerings and those closing soon, and recent awards. Administrators also see budget utilization per provider, slots across terms, and record counts.</p>
    </x-help.topic>

    <x-help.topic id="sf-applications" title="Applications list">
        <p>Select <strong>Applications</strong> in the sidebar. Each row is one scholarship offering together with the renewal offerings that continue it, with the total number of applications across them. Select a row to open its latest offering.</p>
        <p>The review pages show a <strong>tab for each offering</strong> in the group (the newest four, with <strong>View all</strong> for older ones), so you can move between terms without going back.</p>
        <x-help.note tone="tip">
            <p>A renewal offering opens its <a href="#sf-renewals" class="font-500 text-navy-700 hover:underline">Renewals page</a> instead. If it also takes new applicants, its <strong>New applications</strong> tab opens the queue and shortlist.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="sf-workspace" title="Queue and shortlist">
        <p>An offering’s workspace has two tabs: the <strong>Queue</strong> (applications to screen) and the <strong>Shortlist</strong> (applicants you endorsed).</p>

        <x-help.screen url="/staff/applications/offerings/12" caption="The queue: filter, select applications for bulk actions, and open one to review." variant="staff" active="Applications">
            <p class="font-display text-[14px] font-600">CHED Merit Scholarship · Batch 1</p>
            <div class="mt-2 flex gap-1 border-b border-line text-[10px] font-500">
                <span class="border-b-2 border-navy-600 px-2 pb-1 text-navy-700">Batch 1</span>
                <span class="px-2 pb-1 text-slate">2nd sem renewal</span>
            </div>
            <x-help.mock.card class="mt-2.5">
                <div class="flex flex-wrap items-center gap-1.5 border-b border-line p-2">
                    <div class="flex rounded-[6px] bg-paper p-0.5">
                        <span class="rounded-[5px] bg-surface px-2 py-0.5 text-[10px] font-500 shadow-sm">Queue <span class="font-mono text-[9px] text-amber-600">14</span></span>
                        <span class="px-2 py-0.5 text-[10px] font-500 text-slate">Shortlist <span class="font-mono text-[9px] text-mist">8</span></span>
                    </div>
                    <x-help.marker n="1" />
                    <span class="ml-auto flex h-6 w-[110px] items-center gap-1 rounded-[6px] border border-line px-2 text-[9.5px] text-mist"><x-ui.icon name="search" class="size-3" /> Search</span>
                    <span class="flex h-6 items-center gap-1 rounded-[6px] border border-line px-2 text-[9.5px]">All colleges <x-ui.icon name="chevron-down" class="size-3 text-mist" /></span>
                    <x-help.marker n="2" />
                </div>
                <div class="flex items-center gap-2 border-b border-line bg-navy-50/60 px-3 py-1.5 text-[10px]">
                    <span><span class="font-mono">2</span> selected</span>
                    <x-help.mock.button variant="ghost">Verify documents</x-help.mock.button>
                    <x-help.mock.button variant="ghost">Notify incomplete</x-help.mock.button>
                    <x-help.marker n="3" />
                </div>
                @foreach ([['Maria Santos', '21-00418', 'CICS', 'Screening', 'amber', true, 'Grantee elsewhere'], ['Jose Reyes', '22-01077', 'CBEA', 'Submitted', 'amber', true, null], ['Ana Lopez', '20-00912', 'CTE', 'Screening', 'amber', false, 'National grantee']] as [$name, $id, $college, $status, $tone, $checked, $flag])
                    <div class="flex items-center gap-2 border-b border-line px-3 py-1.5 last:border-0">
                        <span @class(['flex size-3 items-center justify-center rounded-[3px] border', 'border-navy-600 bg-navy-600 text-white' => $checked, 'border-line' => ! $checked])>@if ($checked)<x-ui.icon name="check" class="size-2" />@endif</span>
                        <div class="min-w-0 flex-1"><p class="truncate text-[10.5px] font-500">{{ $name }} @if ($flag)<span class="ml-1 rounded-[4px] bg-amber-600/10 px-1 text-[8.5px] text-amber-600">{{ $flag }}</span>@endif</p><p class="font-mono text-[9px] text-mist">{{ $id }}</p></div>
                        <span class="hidden text-[10px] text-slate sm:inline">{{ $college }}</span>
                        <x-help.mock.stamp :tone="$tone">{{ $status }}</x-help.mock.stamp>
                        @if ($loop->first)<x-help.marker n="4" />@endif
                    </div>
                @endforeach
            </x-help.mock.card>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Switch between <strong>Queue</strong> and <strong>Shortlist</strong>. The counts show how many are in each.</x-help.step>
            <x-help.step n="2">Search by name or Student ID, and filter by college and status.</x-help.step>
            <x-help.step n="3">Tick applications for bulk actions: <strong>Verify documents</strong> marks every pending document verified; <strong>Notify incomplete</strong> emails applicants with a rejected document what to fix.</x-help.step>
            <x-help.step n="4">Select a row to open the application’s review page. <strong>Grantee elsewhere</strong> and <strong>National grantee</strong> flags mean the student already holds another scholarship.</x-help.step>
        </x-help.steps>
    </x-help.topic>

    <x-help.topic id="sf-review" title="Reviewing an application">
        <p>The review page shows everything about one application: the applicant, their answers, the screening result, their other scholarships, and every document in full.</p>

        <x-help.screen url="/staff/applications/48/review" caption="The review page: documents shown in place with Verify and Reject, and the decision card on the right." variant="staff" active="Applications">
            <p class="text-[10px] text-slate">← Queue</p>
            <div class="mt-1 flex items-center gap-2"><p class="font-display text-[14px] font-600">Maria Santos</p><span class="font-mono text-[10px] text-slate">21-00418</span><x-help.mock.stamp tone="amber">Screening</x-help.mock.stamp></div>
            <div class="mt-2.5 grid grid-cols-1 gap-2.5 sm:grid-cols-[1fr_160px]">
                <x-help.mock.card title="Documents" icon="form">
                    <x-slot:meta><x-help.marker n="1" /></x-slot:meta>
                    <div class="p-3">
                        <p class="text-[9.5px] font-600 text-slate">Application documents</p>
                        <div class="mt-1.5 rounded-[8px] border border-line">
                            <div class="flex flex-wrap items-center gap-1.5 border-b border-line px-2.5 py-1.5">
                                <span class="flex-1 text-[10.5px] font-500">Certificate of grades</span>
                                <x-help.mock.button variant="secondary" icon="check">Verify</x-help.mock.button>
                                <x-help.mock.button variant="secondary" icon="x">Reject</x-help.mock.button>
                                <x-help.marker n="2" />
                            </div>
                            <div class="space-y-1.5 bg-paper p-3">
                                <div class="mx-auto max-w-[220px] space-y-1.5 rounded-[4px] bg-surface p-3 shadow-sm">
                                    <span class="block h-1.5 w-2/3 rounded-full bg-line"></span>
                                    <span class="block h-1 w-full rounded-full bg-line"></span>
                                    <span class="block h-1 w-5/6 rounded-full bg-line"></span>
                                    <span class="block h-1 w-full rounded-full bg-line"></span>
                                    <span class="block h-1 w-3/4 rounded-full bg-line"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-help.mock.card>
                <div class="space-y-2.5">
                    <x-help.mock.card title="Decision" icon="check-square">
                        <x-slot:meta><x-help.marker n="3" /></x-slot:meta>
                        <div class="space-y-1.5 p-2.5">
                            <x-help.mock.button class="w-full" icon="shortlist">Endorse</x-help.mock.button>
                            <x-help.mock.button variant="secondary" class="w-full">Reject</x-help.mock.button>
                        </div>
                    </x-help.mock.card>
                    <x-help.mock.card title="Scholarships held" icon="award">
                        <x-slot:meta><x-help.marker n="4" /></x-slot:meta>
                        <div class="p-2.5 text-[10px]"><p class="font-500">Provincial Educational Assistance</p><p class="text-mist">Local Government</p></div>
                    </x-help.mock.card>
                </div>
            </div>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">The <strong>Documents</strong> card shows every upload in full, grouped as Application documents, Additional requirements and Disbursement requirements. Scroll the page from one document to the next. PDFs load page by page as you reach them.</x-help.step>
            <x-help.step n="2"><strong>Verify</strong> each document, or <strong>Reject</strong> it with a reason that tells the applicant what to fix. For documents that need the original, tick <strong>Physical copy received</strong> when it arrives.</x-help.step>
            <x-help.step n="3">Decide: <a href="#sf-decide" class="font-500 text-navy-700 hover:underline">Endorse or Reject</a>.</x-help.step>
            <x-help.step n="4"><strong>Scholarships held</strong> lists the scholarships the student already holds in other programs, with their funding source. The applicant’s other applications are listed too.</x-help.step>
        </x-help.steps>

        <h4>Screening</h4>
        <p>The <strong>Screening</strong> card shows the automatic checks: whether every required document is present, and each eligibility rule (GWA, year level, college, program, income) the application failed, with its message. These results inform your decision; they don’t make it.</p>
    </x-help.topic>

    <x-help.topic id="sf-decide" title="Endorsing or rejecting">
        <ul>
            <li><strong>Endorse</strong> moves the application to the <strong>Shortlist</strong>. The applicant is notified.</li>
            <li><strong>Reject</strong> asks for a reason, which the applicant sees on their application page. The application leaves the queue; this can’t be undone.</li>
        </ul>
        <x-help.note tone="rule" title="National scholarship rule">
            <p>If the applicant already holds a national scholarship and this one is national too, it can’t be endorsed or awarded. The review page says why.</p>
        </x-help.note>
        <x-help.note title="The system doesn’t rank">
            <p>The shortlist is ordered by submission date. There is no score. Staff decide, applicant by applicant.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="sf-shortlist" title="Shortlist requirements and the interview">
        <x-help.screen url="/staff/applications/offerings/12?tab=shortlist" caption="The Shortlist tab: slots, the interview, requirement progress and the actions that unlock the award." variant="staff" active="Applications">
            <x-help.mock.card>
                <div class="flex flex-wrap items-center gap-2 border-b border-line p-2.5">
                    <span class="text-[10px] text-slate">Slots awarded <span class="font-mono text-ink">3 / 10</span></span>
                    <span class="ml-auto"></span>
                    <x-help.mock.button variant="secondary" icon="download">Export shortlist</x-help.mock.button>
                    <x-help.mock.button>Open shortlist requirements</x-help.mock.button>
                    <x-help.marker n="1" />
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line bg-paper px-3 py-1.5 text-[10px] text-slate">
                    <span class="font-500 text-ink">Interview</span>
                    <span class="flex items-center gap-1"><x-ui.icon name="calendar" class="size-3" /> Nov 4, 9:00 AM</span>
                    <span class="flex items-center gap-1"><x-ui.icon name="map-pin" class="size-3" /> OSDW Conference Room</span>
                    <x-help.marker n="2" />
                </div>
                @foreach ([['Maria Santos', '3/3', false], ['Jose Reyes', '1/3', true]] as [$name, $progress, $pending])
                    <div class="flex items-center gap-2 border-b border-line px-3 py-1.5 last:border-0">
                        <span class="flex-1 text-[10.5px] font-500">{{ $name }}</span>
                        <span @class(['font-mono text-[10px]', 'text-amber-600' => $pending, 'text-navy-700' => ! $pending])>{{ $progress }}</span>
                        @if ($loop->first)<x-help.marker n="3" />@endif
                        <x-help.mock.button :variant="$pending ? 'disabled' : 'primary'" icon="award">Award</x-help.mock.button>
                        @if ($loop->first)<x-help.marker n="4" />@endif
                    </div>
                @endforeach
            </x-help.mock.card>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1"><strong>Open shortlist requirements</strong> shows the additional requirements to every shortlisted applicant. It is enabled once the shortlist plus anyone already awarded reaches the number of slots (the shortlist may hold more). This can’t be undone. <strong>Export shortlist</strong> downloads the list as a spreadsheet for deliberation.</x-help.step>
            <x-help.step n="2">The <strong>interview</strong> (one date, venue and instructions for the offering) shows here once scheduled. Administrators see a <strong>Schedule interview</strong> link that opens the offering’s Requirements page.</x-help.step>
            <x-help.step n="3">Each row shows how many shortlist requirements the applicant has complied with. Open the review page to verify uploads, return them with remarks, or tick physical copies received.</x-help.step>
            <x-help.step n="4">Award from here or from the review page. See <a href="#sf-award" class="font-500 text-navy-700 hover:underline">Awarding a scholarship</a>.</x-help.step>
        </x-help.steps>
    </x-help.topic>

    <x-help.topic id="sf-award" title="Awarding a scholarship">
        <p>Select <strong>Award scholarship</strong> on a shortlisted applicant’s review page (or <strong>Award</strong> on the shortlist), then <strong>Record award</strong>. The award is recorded and the notice is emailed right away.</p>
        <x-help.note tone="award" title="Awarding is final">
            <p>There is no accept or decline step. Once recorded, the award stands.</p>
        </x-help.note>
        <p>The button stays disabled, and says why, when:</p>
        <ul>
            <li>the applicant still has shortlist requirements to comply with;</li>
            <li>every slot is already awarded;</li>
            <li>the applicant already holds a national scholarship and this one is national too.</li>
        </ul>
    </x-help.topic>

    <x-help.topic id="sf-renewals" title="Reviewing renewals">
        <p>Each term, scholars renew through a <strong>renewal offering</strong>. Open it from the Applications list to get its <strong>Renewals page</strong>.</p>

        <x-help.screen url="/staff/renewals/offerings/15" caption="The Renewals page: each scholar’s year level, requirement progress and status." variant="staff" active="Applications">
            <p class="font-display text-[14px] font-600">DOST-SEI Merit · 2nd sem renewal</p>
            <x-help.mock.card class="mt-2.5">
                <div class="flex flex-wrap items-center gap-1.5 border-b border-line p-2">
                    <div class="flex rounded-[6px] bg-paper p-0.5 text-[10px] font-500">
                        <span class="rounded-[5px] bg-surface px-2 py-0.5 shadow-sm">Renewal review <span class="font-mono text-[9px] text-amber-600">6</span></span>
                        <span class="px-2 py-0.5 text-slate">Renewed <span class="font-mono text-[9px] text-mist">12</span></span>
                        <span class="hidden px-2 py-0.5 text-slate sm:inline">Denied <span class="font-mono text-[9px] text-mist">1</span></span>
                    </div>
                </div>
                @foreach ([['Carlo Mendoza', '20-00311', '4th', true, '2/2', 'Under review'], ['Liza Garcia', '22-00654', '2nd', false, '1/2', 'Under review']] as [$name, $id, $year, $last, $req, $status])
                    <div class="flex items-center gap-2 border-b border-line px-3 py-1.5 last:border-0">
                        <div class="min-w-0 flex-1"><p class="truncate text-[10.5px] font-500">{{ $name }}</p><p class="font-mono text-[9px] text-mist">{{ $id }}</p></div>
                        <span class="text-[10px] text-slate">{{ $year }} @if ($last)<span class="text-amber-600">· Last term</span>@endif</span>
                        <span class="font-mono text-[10px]">{{ $req }}</span>
                        <x-help.mock.stamp tone="amber">{{ $status }}</x-help.mock.stamp>
                    </div>
                @endforeach
            </x-help.mock.card>
        </x-help.screen>

        <ul>
            <li>Each row shows the year level the scholar entered (<strong>Last term</strong> marks 4th year in a second semester), how many renewal requirements are complied, and how many uploads wait for review.</li>
            <li>Select a scholar to open their <strong>renewal review page</strong>: verify or return each upload, tick physical copies received.</li>
            <li><strong>Renew scholarship</strong> records the award and emails the scholar. It takes no slot, and is enabled once every renewal requirement is complied and the scholar has entered their year level.</li>
            <li><strong>Deny renewal</strong> needs a reason, such as graduated, a failing grade, or no longer enrolled. Denying is how a scholar’s renewals end.</li>
        </ul>
    </x-help.topic>

    <x-help.topic id="sf-grantees" title="Grantees, results and disbursement">
        <p><strong>Grantees</strong> lists every recorded award: one row per grantee per offering group, at their latest award.</p>

        <x-help.screen url="/staff/grantees" caption="Grantees filtered to one offering, with the results, disbursement-requirement and disburse actions." variant="staff" active="Grantees">
            <div class="flex flex-wrap items-center gap-1.5">
                <p class="mr-auto font-display text-[15px] font-600">Grantees</p>
                <x-help.mock.button variant="secondary">Publish results</x-help.mock.button>
                <x-help.mock.button variant="secondary">Open disbursement requirements</x-help.mock.button>
                <x-help.mock.button>Disburse all</x-help.mock.button>
                <x-help.marker n="1" />
            </div>
            <x-help.mock.card class="mt-2.5">
                <div class="flex flex-wrap items-center gap-1.5 border-b border-line p-2">
                    <span class="flex h-6 w-[110px] items-center gap-1 rounded-[6px] border border-line px-2 text-[9.5px] text-mist"><x-ui.icon name="search" class="size-3" /> Search grantees</span>
                    <span class="flex h-6 items-center gap-1 rounded-[6px] border border-line px-2 text-[9.5px]">CHED Merit Scholarship <x-ui.icon name="chevron-down" class="size-3 text-mist" /></span>
                    <span class="flex h-6 items-center gap-1 rounded-[6px] border border-line px-2 text-[9.5px]">Batch 1 <x-ui.icon name="chevron-down" class="size-3 text-mist" /></span>
                </div>
                @foreach ([['Maria Santos', 'CICS', '2/2', false], ['Pedro Cruz', 'CBEA', null, true]] as [$name, $college, $req, $done])
                    <div class="flex items-center gap-2 border-b border-line px-3 py-1.5 last:border-0">
                        <span class="flex-1 text-[10.5px] font-500">{{ $name }}</span>
                        <span class="hidden text-[10px] text-slate sm:inline">{{ $college }}</span>
                        <span class="font-mono text-[10px]">₱5,000.00</span>
                        @if ($done)
                            <x-help.mock.stamp tone="gold">Disbursed</x-help.mock.stamp>
                        @else
                            <span class="rounded-[5px] border border-line px-1.5 py-0.5 font-mono text-[9.5px] text-navy-700">{{ $req }}</span>
                            <x-help.marker n="2" />
                        @endif
                        <span class="text-[10px] font-500 text-navy-700">View</span>
                        @if ($loop->first)<x-help.marker n="3" />@endif
                    </div>
                @endforeach
            </x-help.mock.card>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Filter to an offering to see its actions. <strong>Publish results</strong> and <strong>Open disbursement requirements</strong> are enabled once every slot is awarded. <strong>Disburse all</strong> is enabled once every slot is awarded and every grantee has complied with every disbursement requirement.</x-help.step>
            <x-help.step n="2">The <strong>disbursement requirements</strong> count opens a window where you verify or return each upload and mark physical copies received.</x-help.step>
            <x-help.step n="3"><strong>View</strong> opens the grantee’s page: their information, then each application and renewal with its form, documents and requirements, and a <strong>Disburse</strong> button on each award.</x-help.step>
        </x-help.steps>

        <h4>Publishing results</h4>
        <p>Publishing puts the offering’s awardees on the public Results page, with your written instructions for awardees first. Only awardees who agreed are named; the rest are counted. You can edit the instructions or <strong>Unpublish results</strong> at any time.</p>

        <h4>Disbursing</h4>
        <p>Disbursing records that the award was released; it can’t be undone. The grantee is notified and their status reads <strong>Disbursed</strong>. Renewals need no disbursement requirements.</p>

        <x-help.note>
            <p>Search by name or Student ID. The scholarship program and offering filters, and the college filter, are the same ones the Reports page uses.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="sf-announcements" title="Announcements">
        <p>Select <strong>Announcements</strong>, then <strong>Add announcement</strong>.</p>
        <ul>
            <li><strong>Applies to</strong>: a general announcement goes to every student and shows on the landing page. An announcement for one scholarship goes to everyone who applied to it, and shows on its details page and their application pages.</li>
            <li><strong>Save announcement</strong> publishes it and notifies students the first time. <strong>Save as draft</strong> keeps it hidden.</li>
            <li>The list searches by title and filters by All, Published and Drafts. Unpublishing takes an announcement off the site.</li>
        </ul>
    </x-help.topic>

    <x-help.topic id="sf-reports" title="SUC-Levelling report">
        <p><strong>Reports</strong> shows the SUC-Levelling report, in the column format the agency requires. It uses the same filters and rows as the Grantees page.</p>

        <x-help.screen url="/staff/reports" caption="The SUC-Levelling report, with Print report and Export .xlsx." variant="staff" active="Reports">
            <div class="flex flex-wrap items-center gap-1.5">
                <p class="mr-auto font-display text-[15px] font-600">Reports</p>
                <x-help.mock.button variant="secondary" icon="printer">Print report</x-help.mock.button>
                <x-help.mock.button icon="download">Export .xlsx</x-help.mock.button>
                <x-help.marker n="1" />
            </div>
            <x-help.mock.card class="mt-2.5 overflow-x-hidden">
                <div class="grid grid-cols-[28px_1fr_1fr_50px_70px] gap-2 border-b border-line bg-paper px-3 py-1.5 text-[9px] font-600 text-slate">
                    <span>No.</span><span>Last Name</span><span>First Name</span><span>Sex</span><span class="text-right">Amount</span>
                </div>
                @foreach ([['Santos', 'Maria', 'Female'], ['Cruz', 'Pedro', 'Male'], ['Reyes', 'Jose', 'Male']] as [$last, $first, $sex])
                    <div class="grid grid-cols-[28px_1fr_1fr_50px_70px] gap-2 border-b border-line px-3 py-1.5 text-[10px] last:border-0">
                        <span class="font-mono">{{ $loop->iteration }}</span><span>{{ $last }}</span><span>{{ $first }}</span><span>{{ $sex }}</span><span class="text-right font-mono">₱5,000.00</span>
                    </div>
                @endforeach
            </x-help.mock.card>
        </x-help.screen>

        <x-help.steps>
            <x-help.step n="1">Filter the rows, then <strong>Print report</strong> (A4 landscape, with the OSDW header) or <strong>Export .xlsx</strong>. Both use exactly the rows shown.</x-help.step>
        </x-help.steps>
        <x-help.note tone="tip">
            <p>To save the printout as a PDF, choose <strong>Save as PDF</strong> as the printer.</p>
        </x-help.note>
    </x-help.topic>

    <x-help.topic id="sf-account" title="Notifications and your profile">
        <h4>Notifications</h4>
        <p>Every staff and administrator account is notified when a new scholarship program is created, when an offering opens or closes for applications (with the number of applications to review), and when an offering’s interview is scheduled or moved. Select a notification to open the offering’s workspace.</p>

        <h4>Profile</h4>
        <p>Select your initials, then <strong>Profile</strong>, to edit your name, change your password (your current password is required), or connect or switch your Google account. Only the administrator can change your email.</p>
    </x-help.topic>

</x-help.chapter>
