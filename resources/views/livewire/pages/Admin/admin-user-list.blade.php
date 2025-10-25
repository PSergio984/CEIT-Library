<div class="p-6">
    <x-mary-header title="Student List" subtitle="Manage all students and their accounts" separator />

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="stats shadow bg-base-200">
            <div class="stat">
                <div class="stat-figure text-primary">
                    <x-mary-icon name="o-user-group" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Students</div>
                <div class="stat-value text-primary">{{ $this->totalStudents }}</div>
                <div class="stat-desc">Registered in the system</div>
            </div>
        </div>

        <div class="stats shadow bg-base-200">
            <div class="stat">
                <div class="stat-figure text-secondary">
                    <x-mary-icon name="o-book-open" class="w-8 h-8" />
                </div>
                <div class="stat-title">Active Borrowers</div>
                <div class="stat-value text-secondary">{{ $this->totalBorrowers }}</div>
                <div class="stat-desc">Currently borrowing books</div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <x-mary-input label="Search" wire:model.live.debounce.300ms="search"
                    placeholder="Search by name or email..." icon="o-magnifying-glass" />
            </div>

            <div>
                <x-mary-select label="Account Status" wire:model.live="statusFilter" :options="[
                    ['id' => '', 'name' => 'All Status'],
                    ['id' => 'active', 'name' => 'Active'],
                    ['id' => 'suspended', 'name' => 'Suspended'],
                ]" option-value="id"
                    option-label="name" />
            </div>

            <div>
                <x-mary-select label="Credit Score" wire:model.live="creditScoreFilter" :options="[
                    ['id' => '', 'name' => 'All Scores'],
                    ['id' => 'high', 'name' => 'High (75-100)'],
                    ['id' => 'medium', 'name' => 'Medium (50-74)'],
                    ['id' => 'low', 'name' => 'Low (0-49)'],
                ]"
                    option-value="id" option-label="name" />
            </div>

            <div>
                <x-mary-select label="User Role" wire:model.live="roleFilter" :options="[
                    ['id' => '', 'name' => 'All Roles'],
                    ['id' => 'student', 'name' => 'Student'],
                    ['id' => 'admin', 'name' => 'Admin'],
                ]" option-value="id"
                    option-label="name" />
            </div>

            <div class="flex items-end">
                <x-mary-button wire:click="clearFilters" class="btn-outline w-full" icon="o-x-mark">
                    Clear Filters
                </x-mary-button>
            </div>
        </div>
    </div>

    <div class="mb-4 text-xs sm:text-sm text-base-content/70">
        Showing {{ $this->students->count() }} of {{ $this->students->total() }} results
    </div>

    <div class="block lg:hidden space-y-4">
        @foreach ($this->students as $student)
            <div class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3 flex-1">
                        <div>
                            <h3 class="font-semibold text-base">{{ $student['name'] }}</h3>
                            <p class="text-sm text-base-content/70">{{ $student['student_number'] }}</p>
                        </div>
                    </div>
                    @if ($student['is_admin'])
                        <span class="badge badge-info badge-sm">Admin</span>
                    @endif
                </div>

                <div class="mb-3">
                    <p class="text-sm text-base-content/70">{{ $student['email'] }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div>
                        <p class="text-xs text-base-content/50 font-medium mb-1">Credit Score</p>
                        <p class="text-lg font-bold">{{ $student['credit_score'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-base-content/50 font-medium mb-1">Status</p>
                        <span
                            class="badge badge-{{ $student['account_status_label'] == 'Available' ? 'success' : 'error' }} badge-sm">
                            {{ $student['account_status_label'] }}
                        </span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <x-mary-button wire:click="showTransactionDetails({{ $student['id'] }})"
                        class="btn-sm btn-ghost flex-1" icon="o-eye">
                        View
                    </x-mary-button>
                    <x-mary-button wire:click="editStudent({{ $student['id'] }})" class="btn-sm btn-ghost flex-1"
                        icon="o-pencil">
                        Edit
                    </x-mary-button>
                    <x-mary-button wire:click="confirmDelete({{ $student['id'] }})"
                        class="btn-sm btn-ghost text-error flex-1" icon="o-trash">
                        Delete
                    </x-mary-button>
                </div>
            </div>
        @endforeach

        <div class="mt-6">
            {{ $this->students->links() }}
        </div>
    </div>

    <div class="hidden lg:block overflow-x-auto">
        <x-mary-table :headers="$headers" :rows="$this->students" :sort-by="$sortBy" with-pagination striped
            row-class="hover:bg-base-200" header-class="text-base-content bg-base-200"
            class="w-full min-w-fit table-auto">

            @scope('cell_id', $row)
                <span class="text-base-content/70">{{ $row['id'] }}</span>
            @endscope

            @scope('cell_student_number', $row)
                <span class="font-mono text-sm">{{ $row['student_number'] }}</span>
            @endscope

            @scope('cell_name', $row)
                <div class="flex items-center gap-3">
                    <div>
                        <div class="font-medium">{{ $row['name'] }}</div>
                        @if ($row['is_admin'])
                            <span class="badge badge-info badge-xs">Admin</span>
                        @endif
                    </div>
                </div>
            @endscope

            @scope('cell_email', $row)
                <div class="text-sm text-base-content/70">{{ $row['email'] }}</div>
            @endscope

            @scope('cell_credit_score', $row)
                <div class="flex items-center gap-2">
                    <span class="font-bold text-lg">{{ $row['credit_score'] }}</span>
                    <div class="radial-progress text-{{ $row['credit_score'] >= 75 ? 'success' : ($row['credit_score'] >= 50 ? 'warning' : 'error') }}"
                        style="--value:{{ $row['credit_score'] }}; --size:2rem; --thickness: 3px;">
                    </div>
                </div>
            @endscope

            @scope('cell_status', $row)
                <span class="badge badge-{{ $row['account_status_label'] == 'Available' ? 'success' : 'error' }}">
                    {{ $row['account_status_label'] }}
                </span>
            @endscope

            @scope('cell_actions', $row)
                <div class="flex gap-1">
                    <x-mary-button wire:click="showTransactionDetails({{ $row['id'] }})" class="btn-sm btn-ghost"
                        icon="o-eye" tooltip="View Details" />
                    <x-mary-button wire:click="editStudent({{ $row['id'] }})" class="btn-sm btn-ghost" icon="o-pencil"
                        tooltip="Edit Student" />
                    <x-mary-button wire:click="confirmDelete({{ $row['id'] }})" class="btn-sm btn-ghost text-error"
                        icon="o-trash" tooltip="Delete Student" />
                </div>
            @endscope
        </x-mary-table>
    </div>

    @if ($this->students->isEmpty())
        <div class="text-center py-12">
            <x-mary-icon name="o-user-group" class="w-16 h-16 mx-auto text-base-content/30 mb-4" />
            <h3 class="text-lg font-medium mb-2">No students found</h3>
            <p class="text-base-content/70 mb-4">Try adjusting your search criteria or filters.</p>
            <x-mary-button wire:click="clearFilters" class="btn-outline">
                Clear All Filters
            </x-mary-button>
        </div>
    @endif

    @if ($showStudentModal && $selectedStudent)
        <x-mary-modal wire:model="showStudentModal" title="Student Details" class="backdrop-blur">
            <div class="space-y-6 pb-28 sm:pb-0">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <div class="text-sm text-base-content/60 mb-1">Student Name</div>
                        <div class="font-semibold">{{ $selectedStudent->first_name }}
                            {{ $selectedStudent->last_name }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-base-content/60 mb-1">Email</div>
                        <div>{{ $selectedStudent->email }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-base-content/60 mb-1">Credit Score</div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-xl">{{ $selectedStudent->credit_score }}</span>
                            <span
                                class="badge badge-{{ $selectedStudent->credit_score >= 75 ? 'success' : 'error' }}">
                                {{ $selectedStudent->credit_score >= 75 ? 'Available' : 'Suspended' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Currently Borrowed Books -->
                <div>
                    <h3 class="font-semibold text-lg mb-3">Currently Borrowed Books</h3>
                    <div class="space-y-2">
                        @forelse($selectedStudent->borrowTransactions as $transaction)
                            @php
                                $dueDate = $transaction->time_in?->copy()->addDays(7);
                                $isOverdue = $dueDate && $dueDate->isPast();
                                $isDueSoon = $dueDate && $dueDate->diffInDays(now()) <= 2;
                            @endphp
                            <div class="flex justify-between items-center p-3 bg-base-200 rounded-lg">
                                <div>
                                    <div class="font-medium">
                                        {{ $transaction->inventory?->academicPaper?->title ?? 'Unknown Title' }}</div>
                                    <div class="text-sm text-base-content/60">
                                        {{ $transaction->inventory?->academicPaper?->paper_type ?? 'N/A' }}
                                    </div>
                                </div>
                                <span
                                    class="badge badge-{{ $isOverdue ? 'error' : ($isDueSoon ? 'warning' : 'success') }}">
                                    {{ $isOverdue ? 'Overdue' : ($isDueSoon ? 'Due Soon' : 'Ongoing') }}
                                </span>
                            </div>
                        @empty
                            <div class="text-center py-6 text-base-content/60">
                                <x-mary-icon name="o-book-open" class="w-12 h-12 mx-auto mb-2 opacity-50" />
                                <p>No books currently borrowed</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <x-slot:actions>
                    <div class="sticky bottom-0 left-0 right-0 px-2 pb-4 bg-white/90 sm:bg-transparent z-50">
                        <div class="flex flex-row gap-2 w-full">
                            <x-mary-button label="Close" @click="$wire.closeModal()" class="w-1/2 sm:w-auto" icon="o-x-mark" variant="outline" />
                            <x-mary-button label="Transaction History" class="btn-primary w-1/2 sm:w-auto" icon="o-clock" />
                        </div>
                    </div>
                </x-slot:actions>
            </div>
        </x-mary-modal>
    @endif

    @if ($showEditModal)
        <x-mary-modal wire:model="showEditModal" title="Edit User" class="backdrop-blur">
            <div class="space-y-4">
                <x-mary-input label="Student ID" wire:model="studentId" readonly disabled />

                <div class="grid grid-cols-2 gap-4">
                    <x-mary-input label="First Name" wire:model="firstName" />
                    <x-mary-input label="Last Name" wire:model="lastName" />
                </div>

                <x-mary-input label="Email" wire:model="email" type="email" />

                <div class="grid grid-cols-2 gap-4">
                    <x-mary-input label="Credit Score" wire:model="creditScore" type="number" min="0"
                        max="100" />

                    <x-mary-select label="Account Status" wire:model="accountStatus" :options="[['id' => 'active', 'name' => 'Active'], ['id' => 'suspended', 'name' => 'Suspended']]"
                        option-value="id" option-label="name" />
                </div>

                <x-mary-checkbox label="Admin User" wire:model="isAdmin" />
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.closeModal()" />
                <x-mary-button label="Save Changes" wire:click="saveChanges" class="btn-primary"
                    spinner="saveChanges" />
            </x-slot:actions>
        </x-mary-modal>
    @endif

    @if ($showDeleteModal && $selectedStudent)
        <x-mary-modal wire:model="showDeleteModal" title="Delete User?" class="backdrop-blur">
            <div class="space-y-4">
                <div class="alert alert-warning">
                    <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6" />
                    <span>This action cannot be undone!</span>
                </div>

                <p class="text-base-content/80">
                    Once deleted, all information and resources related to
                    <strong>{{ $selectedStudent->first_name }} {{ $selectedStudent->last_name }}</strong>
                    will be permanently removed from the system.
                </p>

                <p class="text-base-content/80">
                    Please confirm you want to proceed with deleting this user.
                </p>
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.closeModal()" />
                <x-mary-button label="Delete User" wire:click="deleteUser" class="btn-error" icon="o-trash"
                    spinner="deleteUser" />
            </x-slot:actions>
        </x-mary-modal>
    @endif
</div>
