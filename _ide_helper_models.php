<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $catalog_code
 * @property string $title
 * @property int $publication_year
 * @property string $paper_type
 * @property string $research_project_adviser
 * @property string $department
 * @property string $dean
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Author> $authors
 * @property-read int|null $authors_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory> $copies
 * @property-read int|null $copies_count
 * @property-read mixed $available_copies_count
 * @property-read mixed $total_copies_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper byDepartment($department)
 * @method static \Database\Factories\AcademicPaperFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper search($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereCatalogCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereDean($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereDepartment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper wherePaperType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper wherePublicationYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereResearchProjectAdviser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class AcademicPaper extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $time_in
 * @property \Illuminate\Support\Carbon|null $time_out
 * @property string $status
 * @property int|null $scanned_by
 * @property int|null $duration_minutes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Librarian|null $scannedByLibrarian
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\AttendanceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereScannedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereTimeIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereTimeOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereUserId($value)
 * @mixin \Eloquent
 */
	class Attendance extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\AuthorFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Author whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class Author extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $academic_paper_id
 * @property int $inventory_id
 * @property \Illuminate\Support\Carbon|null $time_in
 * @property \Illuminate\Support\Carbon|null $time_out
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 * @property string $session_token
 * @property string|null $notes
 * @property int|null $duration_minutes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AcademicPaper $academicPaper
 * @property-read \App\Models\Inventory $inventory
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\BorrowTransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereAcademicPaperId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereInventoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereSessionToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereTimeIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereTimeOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereUserId($value)
 * @mixin \Eloquent
 */
	class BorrowTransaction extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $academic_paper_id
 * @property int $copy_number
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AcademicPaper $academicPaper
 * @method static \Database\Factories\InventoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereAcademicPaperId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereCopyNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class Inventory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $shift_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian active()
 * @method static \Database\Factories\LibrarianFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereShiftNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereUserId($value)
 * @mixin \Eloquent
 */
	class Librarian extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RuleRegulation> $ruleRegulations
 * @property-read int|null $rule_regulations_count
 * @method static \Database\Factories\RuleHeaderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RuleHeader newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RuleHeader newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RuleHeader query()
 */
	class RuleHeader extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \App\Models\RuleHeader|null $ruleHeader
 * @method static \Database\Factories\RuleRegulationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RuleRegulation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RuleRegulation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RuleRegulation query()
 */
	class RuleRegulation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property int $score_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $status
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement byScoreRange($minScore = null, $maxScore = null)
 * @method static \Database\Factories\ScoreIncrementFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement goodStanding()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereScoreValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereUserId($value)
 * @mixin \Eloquent
 * @property-read \App\Models\Attendance|null $attendance
 * @property-read \App\Models\BorrowTransaction|null $borrowTransaction
 */
	class ScoreIncrement extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property int $credit_score
 * @property string $account_status
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BorrowTransaction> $borrowTransactions
 * @property-read int|null $borrow_transactions_count
 * @property-read \App\Models\ScoreIncrement|null $creditScore
 * @property-read \App\Models\Librarian|null $librarianDuty
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendance> $librarySessions
 * @property-read int|null $library_sessions_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ViolationTransaction> $violations
 * @property-read int|null $violations_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreditScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendance> $attendances
 * @property-read int|null $attendances_count
 * @property-read \App\Models\Role|null $role
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAccountStatus($value)
 */
	class User extends \Eloquent implements \Illuminate\Contracts\Auth\MustVerifyEmail {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $penalty_score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $severity
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ViolationTransaction> $userViolations
 * @property-read int|null $user_violations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation byPenalty($minPenalty = null, $maxPenalty = null)
 * @method static \Database\Factories\ViolationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation wherePenaltyScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class Violation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $violation_id
 * @property int|null $attendance_id
 * @property \Illuminate\Support\Carbon $date_occurred
 * @property string $severity
 * @property string|null $remarks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Violation $violation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction byDateRange($startDate = null, $endDate = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction bySeverity($severity)
 * @method static \Database\Factories\ViolationTransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction recent($days = 30)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereDateOccurred($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereSeverity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereViolationId($value)
 * @mixin \Eloquent
 * @property-read \App\Models\Attendance|null $attendance
 */
	class ViolationTransaction extends \Eloquent {}
}

