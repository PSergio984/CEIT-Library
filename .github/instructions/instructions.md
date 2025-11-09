# Laravel Livewire Component Refactoring Prompt

## Objective

Refactor this Laravel Livewire component to align with production-grade best practices, focusing on performance optimization, proper state management, and the fundamental distinction between server-side and client-side state.
pls remember im using maryui component library

---

## Core Philosophy: Server State vs. Client State

**The Golden Rule:** _"If I didn't need to make a network request in JavaScript for this, I don't need a Livewire component for it."_

**Livewire is designed for managing SERVER STATE, not CLIENT STATE.**

### Server State (Use Livewire)

- Data that requires database queries
- Information that needs server-side authorization
- Content that must be validated or processed by PHP
- State that needs to persist across sessions
- Operations requiring backend business logic

### Client State (Use Alpine.js)

- Modal visibility toggles
- Dropdown open/closed states
- Tab switching (without data fetching)
- Accordion expand/collapse
- Form field show/hide logic
- Tooltip visibility
- Loading animations and UI transitions
- Temporary client-side filtering (before applying)
- Any purely presentational UI changes

**Key Question to Ask:** _"Does this interaction require communication with the server?"_

- **NO** → Use Alpine.js (instant, zero latency)
- **YES** → Use Livewire (but optimize it)

---

## Refactoring Checklist

### 1. Security Audit

#### Authorization

- [ ] **Implement server-side authorization** for ALL actions that modify data
- [ ] Use Laravel Policies with `$this->authorize()` in every destructive method
- [ ] Never trust client-side parameters - always retrieve and verify models from database
- [ ] Use `#[Locked]` attribute on properties that shouldn't be modified by client (e.g., user IDs, model IDs)

```php
// ❌ INSECURE
public function delete($postId) {
    Post::find($postId)->delete();
}

// ✅ SECURE
public function delete($postId) {
    $post = Post::findOrFail($postId);
    $this->authorize('delete', $post);
    $post->delete();
}
```

#### State Protection

- [ ] Mark server-authoritative properties with `#[Locked]`
- [ ] Validate ALL input data before processing
- [ ] Implement CSRF protection (built-in with Livewire)

---

### 2. Performance Optimization

#### Minimize Payload Size (CRITICAL)

- [ ] **Remove ALL Eloquent models from public properties**
- [ ] Convert data collections to `#[Computed]` properties
- [ ] Keep public properties primitive (strings, integers, booleans, arrays)
- [ ] Avoid storing large arrays or complex objects in public state

```php
// ❌ BLOATED PAYLOAD
public $posts; // Entire Eloquent collection serialized on every request

public function mount() {
    $this->posts = Post::with('author')->get();
}

// ✅ OPTIMIZED
#[Computed]
public function posts() {
    return Post::with('author')
        ->where('user_id', auth()->id())
        ->get();
}
```

**Why This Matters:**

- Every public property is serialized to JSON and sent in BOTH directions (client → server → client)
- A collection of 50 posts can create a 100KB+ payload
- Computed properties are fetched on-demand and NOT included in the wire snapshot

#### Database Query Optimization

- [ ] **Eradicate N+1 queries** using `with()` for eager loading
- [ ] Use Laravel Debugbar/Telescope to verify query count
- [ ] Add database indexes on columns used in `where()`, `orderBy()`, `orWhere()`
- [ ] Use `select()` to retrieve only necessary columns
- [ ] Implement pagination for large datasets
- [ ] Cache expensive queries with `#[Computed(persist: true)]`

```php
// ❌ N+1 QUERY PROBLEM
#[Computed]
public function posts() {
    return Post::where('user_id', auth()->id())->get();
    // In Blade: {{ $post->author->name }} triggers N additional queries
}

// ✅ EAGER LOADED
#[Computed]
public function posts() {
    return Post::with('author', 'comments.user')
        ->where('user_id', auth()->id())
        ->get();
}
```

#### Reduce Server Roundtrips

- [ ] **Default to `wire:model` (deferred)** instead of `wire:model.live`
- [ ] Use `wire:model.live.debounce.300ms` for search inputs
- [ ] Use `wire:model.blur` for field-level validation
- [ ] Consolidate multiple filter inputs with an "Apply Filters" button
- [ ] Remove unnecessary `wire:click` events for UI-only changes

```php
// ❌ CHATTY COMPONENT (3+ server requests)
<select wire:model.live="status">...</select>
<select wire:model.live="category">...</select>
<select wire:model.live="sortBy">...</select>

// ✅ OPTIMIZED (1 server request)
<select wire:model="status">...</select>
<select wire:model="category">...</select>
<select wire:model="sortBy">...</select>
<button wire:click="applyFilters">Apply Filters</button>
```

**wire:model Decision Matrix:**

| Modifier                         | Use Case                 | Requests per Interaction      |
| -------------------------------- | ------------------------ | ----------------------------- |
| `wire:model`                     | Default for forms        | 0 (sent with form submission) |
| `wire:model.blur`                | Field validation         | 1 (on focus loss)             |
| `wire:model.live.debounce.300ms` | Search/autocomplete      | 1 (after 300ms pause)         |
| `wire:model.live`                | Real-time username check | Many (every keystroke)        |

---

### 3. Offload to Alpine.js (THE BIGGEST WIN)

#### Move Client-Side State to Alpine

- [ ] **Remove Livewire public properties for modal visibility**
- [ ] Use Alpine `x-data` for dropdown/accordion state
- [ ] Handle tab switching with Alpine unless fetching data
- [ ] Manage temporary UI animations with Alpine
- [ ] Use `x-show` / `x-if` for conditional rendering

```php
// ❌ INEFFICIENT (server roundtrip just to show modal)
// Livewire Component
public $showModal = false;

public function openModal() {
    $this->showModal = true;
}

// Blade
<button wire:click="openModal">Open</button>
@if($showModal)
    <div class="modal">...</div>
@endif

// ✅ OPTIMIZED (instant client-side)
// Remove public $showModal from component

// Blade
<div x-data="{ showModal: false }">
    <button @click="showModal = true">Open</button>
    <div x-show="showModal" class="modal">...</div>
</div>
```

#### Livewire-Alpine Communication Patterns

**Pattern 1: Livewire dispatches event → Alpine listens**

```php
// Livewire Component
public function loadUserData($userId) {
    $user = User::findOrFail($userId);
    $this->dispatch('open-edit-modal', user: $user->toArray());
}

// Blade Template
<div
    x-data="{
        show: false,
        userData: {}
    }"
    @open-edit-modal.window="
        show = true;
        userData = $event.detail.user;
    "
>
    <div x-show="show" class="modal">
        <input x-model="userData.name">
        <button @click="$wire.saveUser(userData.id, userData)">Save</button>
    </div>
</div>
```

**Pattern 2: Optimistic UI with Alpine**

```php
// Delete button with optimistic removal
<button
    @click="
        document.getElementById('row-{{ $post->id }}').classList.add('opacity-50');
        $wire.deletePost({{ $post->id }})
    "
>
    Delete
</button>

// Livewire method
public function deletePost($postId) {
    $post = Post::findOrFail($postId);
    $this->authorize('delete', $post);

    if ($post->hasActiveComments()) {
        $this->dispatch('restore-row-' . $postId);
        $this->dispatch('show-error', 'Cannot delete post with active comments');
        return;
    }

    $post->delete();
}
```

---

### 4. Blade Template Best Practices

#### Essential Directives

- [ ] **Always use `wire:key` in loops** with unique, stable identifiers
- [ ] Add `wire:loading` states to all action buttons
- [ ] Use `wire:loading.attr="disabled"` to prevent duplicate submissions
- [ ] Implement `wire:confirm` for destructive actions
- [ ] Use `wire:target` for specific loading states

```blade
{{-- ❌ MISSING wire:key (causes DOM diffing bugs) --}}
@foreach($posts as $post)
    <div>{{ $post->title }}</div>
@endforeach

{{-- ✅ CORRECT --}}
@foreach($posts as $post)
    <div wire:key="post-{{ $post->id }}">
        {{ $post->title }}
    </div>
@endforeach

{{-- Loading states --}}
<button
    wire:click="save"
    wire:loading.attr="disabled"
    wire:target="save"
>
    <span wire:loading.remove wire:target="save">Save</span>
    <span wire:loading wire:target="save">Saving...</span>
</button>

{{-- Destructive action confirmation --}}
<button
    wire:click="delete({{ $post->id }})"
    wire:confirm="Are you sure you want to delete this post?"
>
    Delete
</button>
```

---

### 5. Advanced Optimization Techniques

#### Computed Property Caching

```php
// Cache expensive computations for 5 minutes
#[Computed(persist: true, seconds: 300)]
public function statistics() {
    return [
        'total_users' => User::count(),
        'active_today' => User::whereDate('last_active', today())->count(),
        'revenue' => Order::sum('total')
    ];
}

// Invalidate cache when needed
public function refreshStats() {
    unset($this->statistics);
}
```

#### Cache Versioning Pattern

```php
// More reliable than cache tags
protected function getStatsCacheKey($key): string {
    $version = Cache::rememberForever('admin_stats_version', fn() => 1);
    return "admin_stats_v{$version}:{$key}";
}

protected function clearStatsCache(): void {
    Cache::increment('admin_stats_version'); // Invalidates all v1 keys
}
```

#### Component Decomposition

```php
// ❌ MONOLITHIC COMPONENT
class AdminUserList extends Component {
    // Handles: list display, filtering, edit modal, delete modal, stats
    // 500+ lines of code
}

// ✅ DECOMPOSED
class AdminUserList extends Component {
    // Only handles: list display and filtering
}

class UserStatsCard extends Component {
    // Lazy-loaded: <livewire:user-stats-card lazy />
}

class EditUserModal extends Component {
    // Listens for: 'edit-user' event
}
```

---

### 6. Implementation Priority

**Apply changes in this order for maximum impact:**

1. **Security First** (30 minutes)

   - Add authorization checks
   - Lock sensitive properties
   - Validate inputs

2. **Payload Reduction** (1-2 hours)

   - Convert collections to computed properties
   - Remove Eloquent models from public props
   - **This alone can yield 80-90% payload reduction**

3. **Alpine Migration** (2-3 hours)

   - Move modal state to Alpine
   - Remove wire:model.live from dropdowns
   - Add "Apply Filters" pattern

4. **Database Optimization** (1-2 hours)

   - Eager load relationships
   - Add indexes
   - Verify with Debugbar

5. **Polish UX** (1 hour)
   - Add loading states
   - Add wire:key to loops
   - Add confirmation dialogs

---

## Verification Checklist

After refactoring, verify these metrics:

### Performance Metrics

- [ ] **Payload size reduced by >70%** (check Network tab)
- [ ] **Database queries ≤ 3 per page load** (check Debugbar)
- [ ] **No N+1 queries** (verified with Debugbar)
- [ ] **Initial page load <500ms** (local environment)

### Interaction Metrics

- [ ] Modal open/close has **zero network requests**
- [ ] Filter changes consolidated into **1 request** (not 3-7)
- [ ] Search has **debounce applied** (not firing on every keystroke)

### Code Quality

- [ ] **All loops have wire:key**
- [ ] **All actions have authorization**
- [ ] **All destructive actions have confirmation**
- [ ] **All buttons have loading states**

---

## Example: Before & After

### BEFORE (Problematic Component)

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Post;

class PostManager extends Component
{
    public $posts; // ❌ Entire collection in payload
    public $showModal = false; // ❌ Server roundtrip for UI state
    public $editingPost; // ❌ Full model in payload

    public function mount()
    {
        $this->posts = Post::where('user_id', auth()->id())->get();
        // ❌ Missing eager loading (N+1 queries)
    }

    public function openEditModal($postId) // ❌ No authorization
    {
        $this->editingPost = Post::find($postId);
        $this->showModal = true; // ❌ Server request just to show modal
    }

    public function delete($postId) // ❌ CRITICAL SECURITY FLAW
    {
        Post::find($postId)->delete();
    }

    public function render()
    {
        return view('livewire.post-manager');
    }
}
```

```blade
{{-- Blade Template (BEFORE) --}}
<div>
    @foreach($posts as $post) {{-- ❌ Missing wire:key --}}
        <div>
            <h3>{{ $post->title }}</h3>
            <p>By {{ $post->author->name }}</p> {{-- ❌ N+1 query --}}

            <button wire:click="openEditModal({{ $post->id }})">
                Edit {{-- ❌ No loading state --}}
            </button>

            <button wire:click="delete({{ $post->id }})">
                Delete {{-- ❌ No confirmation --}}
            </button>
        </div>
    @endforeach

    @if($showModal) {{-- ❌ Livewire managing UI state --}}
        <div class="modal">
            <input wire:model.live="editingPost.title"> {{-- ❌ Live on every keystroke --}}
            <button wire:click="save">Save</button>
        </div>
    @endif
</div>
```

### AFTER (Optimized Component)

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PostManager extends Component
{
    use AuthorizesRequests;

    // ✅ No posts property - using computed instead
    // ✅ No showModal - Alpine handles it
    // ✅ No editingPost - fetched on-demand

    #[Computed] // ✅ Not in payload, cached per-request
    public function posts()
    {
        return Post::with('author') // ✅ Eager loading
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    public function loadPostForEdit($postId)
    {
        $post = Post::findOrFail($postId);
        $this->authorize('update', $post); // ✅ Authorization

        // ✅ Dispatch to Alpine (no server state)
        $this->dispatch('open-edit-modal', post: $post->toArray());
    }

    public function save($postId, $data)
    {
        $post = Post::findOrFail($postId);
        $this->authorize('update', $post);

        $validated = validator($data, [
            'title' => 'required|max:255',
            'body' => 'required',
        ])->validate();

        $post->update($validated);

        $this->dispatch('post-updated');
    }

    public function delete($postId)
    {
        $post = Post::findOrFail($postId); // ✅ Retrieve from DB
        $this->authorize('delete', $post); // ✅ Authorization

        $post->delete();
    }

    public function render()
    {
        return view('livewire.post-manager');
    }
}
```

```blade
{{-- Blade Template (AFTER) --}}
<div>
    {{-- ✅ Alpine manages modal state (zero server requests) --}}
    <div
        x-data="{
            showModal: false,
            postData: {}
        }"
        @open-edit-modal.window="
            showModal = true;
            postData = $event.detail.post;
        "
        @post-updated.window="
            showModal = false;
        "
    >
        @foreach($this->posts as $post) {{-- ✅ wire:key present --}}
            <div wire:key="post-{{ $post->id }}">
                <h3>{{ $post->title }}</h3>
                <p>By {{ $post->author->name }}</p> {{-- ✅ No N+1 (eager loaded) --}}

                <button
                    wire:click="loadPostForEdit({{ $post->id }})"
                    wire:loading.attr="disabled"
                    wire:target="loadPostForEdit({{ $post->id }})"
                >
                    {{-- ✅ Loading state --}}
                    <span wire:loading.remove wire:target="loadPostForEdit({{ $post->id }})">
                        Edit
                    </span>
                    <span wire:loading wire:target="loadPostForEdit({{ $post->id }})">
                        Loading...
                    </span>
                </button>

                <button
                    wire:click="delete({{ $post->id }})"
                    wire:confirm="Are you sure you want to delete this post?" {{-- ✅ Confirmation --}}
                    wire:loading.attr="disabled"
                >
                    Delete
                </button>
            </div>
        @endforeach

        {{-- ✅ Alpine-managed modal (instant open/close) --}}
        <div x-show="showModal" x-cloak class="modal">
            <input x-model="postData.title"> {{-- ✅ No server requests while typing --}}

            <button
                @click="$wire.save(postData.id, postData); showModal = false"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>

            <button @click="showModal = false">Cancel</button>
        </div>
    </div>
</div>
```

### Performance Comparison

| Metric              | Before              | After           | Improvement       |
| ------------------- | ------------------- | --------------- | ----------------- |
| **Payload Size**    | 150 KB              | 8 KB            | **94% reduction** |
| **DB Queries**      | 52 queries (N+1)    | 2 queries       | **96% reduction** |
| **Modal Open Time** | 200-500ms (network) | <16ms (instant) | **Instant**       |
| **Filter Changes**  | 3-7 requests        | 1 request       | **85% reduction** |
| **Security Issues** | 3 critical          | 0               | **100% fixed**    |

---

## Final Reminders

### The Decision Tree

```
User interaction happens
    ↓
Does it require server data/logic?
    ├─ NO → Use Alpine.js (instant, zero latency)
    │   Examples: toggle modal, switch tabs, show/hide
    │
    └─ YES → Use Livewire (optimize it)
        ↓
        • Use computed properties for data
        • Add authorization
        • Eager load relationships
        • Add loading states
        • Use wire:model (deferred by default)
```

### Common Pitfalls to Avoid

1. **Don't use Livewire for client-only UI state**
2. **Don't pass Eloquent models to public properties**
3. **Don't use wire:model.live everywhere**
4. **Don't forget wire:key in loops**
5. **Don't trust client-sent parameters**
6. **Don't skip eager loading**
7. **Don't omit loading states**
8. **Don't skip confirmation on destructive actions**

---

## Additional Resources

- [Livewire Security Documentation](https://livewire.laravel.com/docs/security)
- [Livewire Performance Guide](https://livewire.laravel.com/docs/performance)
- [Alpine.js Integration Guide](https://livewire.laravel.com/docs/alpine)
- [Laravel Query Optimization](https://laravel.com/docs/queries#optimizing-queries)

---

_Remember: Livewire + Alpine.js is not an either/or choice. Use both strategically: Livewire for server state, Alpine for client state. This is the path to performant, maintainable applications._
