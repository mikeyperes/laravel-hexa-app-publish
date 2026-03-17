<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\User;
use hexa_core\Services\GenericService;
use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishAccountUser;
use hexa_app_publish\Services\PublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublishAccountController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;

    /**
     * @param GenericService $generic
     * @param PublishService $publishService
     */
    public function __construct(GenericService $generic, PublishService $publishService)
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
    }

    /**
     * List all publishing accounts.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = PublishAccount::with(['owner', 'sites', 'users', 'campaigns']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('account_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $accounts = $query->orderByDesc('created_at')->get();

        return view('app-publish::accounts.index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Show create account form.
     *
     * @return View
     */
    public function create(): View
    {
        $users = User::orderBy('name')->get();

        return view('app-publish::accounts.create', [
            'users' => $users,
        ]);
    }

    /**
     * Store a new publishing account.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'owner_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $validated['account_id'] = PublishAccount::generateAccountId();
        $validated['status'] = 'active';

        $account = PublishAccount::create($validated);

        // Add owner as super_admin on the account
        if ($account->owner_user_id) {
            PublishAccountUser::create([
                'publish_account_id' => $account->id,
                'user_id' => $account->owner_user_id,
                'role' => 'super_admin',
            ]);
        }

        activity_log('publish', 'account_created', "Account created: {$account->name} ({$account->account_id})");

        return response()->json([
            'success' => true,
            'message' => "Account '{$account->name}' created successfully.",
            'account' => $account,
            'redirect' => route('publish.accounts.show', $account->id),
        ]);
    }

    /**
     * Show a single account with its sites, campaigns, and stats.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $account = PublishAccount::with([
            'owner',
            'users.user',
            'sites',
            'campaigns.site',
            'templates',
        ])->findOrFail($id);

        $articleStats = [
            'total' => $account->articles()->count(),
            'published' => $account->articles()->where('status', 'published')->count(),
            'completed' => $account->articles()->where('status', 'completed')->count(),
            'review' => $account->articles()->where('status', 'review')->count(),
            'drafting' => $account->articles()->whereIn('status', ['sourcing', 'drafting', 'spinning'])->count(),
        ];

        return view('app-publish::accounts.show', [
            'account' => $account,
            'articleStats' => $articleStats,
        ]);
    }

    /**
     * Show edit form for an account.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $account = PublishAccount::with(['owner', 'users.user'])->findOrFail($id);
        $users = User::orderBy('name')->get();

        return view('app-publish::accounts.edit', [
            'account' => $account,
            'users' => $users,
        ]);
    }

    /**
     * Update an account.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $account = PublishAccount::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'status' => 'required|in:active,suspended,canceled',
            'owner_user_id' => 'nullable|exists:users,id',
            'plan' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $account->update($validated);

        activity_log('publish', 'account_updated', "Account updated: {$account->name} ({$account->account_id})");

        return response()->json([
            'success' => true,
            'message' => "Account '{$account->name}' updated successfully.",
        ]);
    }

    /**
     * Add a user to an account.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function addUser(Request $request, int $id): JsonResponse
    {
        $account = PublishAccount::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:super_admin,admin,user',
        ]);

        $exists = PublishAccountUser::where('publish_account_id', $account->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'User is already assigned to this account.',
            ], 422);
        }

        PublishAccountUser::create([
            'publish_account_id' => $account->id,
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
        ]);

        $user = User::find($validated['user_id']);
        activity_log('publish', 'user_added', "User '{$user->name}' added to account '{$account->name}' as {$validated['role']}");

        return response()->json([
            'success' => true,
            'message' => "User added to account successfully.",
        ]);
    }

    /**
     * Remove a user from an account.
     *
     * @param int $id
     * @param int $userId
     * @return JsonResponse
     */
    public function removeUser(int $id, int $userId): JsonResponse
    {
        $account = PublishAccount::findOrFail($id);

        $accountUser = PublishAccountUser::where('publish_account_id', $account->id)
            ->where('user_id', $userId)
            ->first();

        if (!$accountUser) {
            return response()->json([
                'success' => false,
                'message' => 'User is not assigned to this account.',
            ], 404);
        }

        $user = User::find($userId);
        $accountUser->delete();

        activity_log('publish', 'user_removed', "User '{$user->name}' removed from account '{$account->name}'");

        return response()->json([
            'success' => true,
            'message' => "User removed from account successfully.",
        ]);
    }
}
