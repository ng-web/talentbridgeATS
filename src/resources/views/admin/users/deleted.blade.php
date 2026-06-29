<x-layouts.portal :title="'User Recycle Bin'" heading="User Recycle Bin" subheading="Restore deleted users or permanently remove accounts when no protected records exist." portalRole="admin">
    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-3xl border border-green-200 bg-green-50 p-5 text-green-900 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-3xl border border-red-200 bg-red-50 p-5 text-red-900 shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Deleted Users</h3>
                    <p class="mt-1 text-sm text-gray-500">Soft-deleted users are hidden from the active user list and can be restored later.</p>
                </div>

                <x-likeslocale.button :href="route('admin.users.index')" variant="secondary">
                    Back to Users
                </x-likeslocale.button>
            </div>
        </div>

        @if($users->isEmpty())
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
                <h3 class="text-xl font-semibold text-gray-900">Recycle bin is empty</h3>
                <p class="mt-2 text-gray-500">Deleted users will appear here.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($users as $user)
                    <x-likeslocale.operation-row>
                        <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-900">{{ $user->name }}</p>
                                    <x-likeslocale.status-pill tone="brand">{{ $user->primaryRoleLabel() }}</x-likeslocale.status-pill>
                                    <x-likeslocale.status-pill tone="danger">Deleted</x-likeslocale.status-pill>
                                </div>

                                <div class="border-t border-gray-100 mt-3 pt-2.5">
                                    <div class="text-sm text-gray-600 flex flex-wrap gap-x-4 gap-y-1">
                                        <span class="text-gray-500"><x-heroicon-o-envelope class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $user->email }}</span>
                                        @if($user->employer?->company_name)
                                            <span><x-heroicon-o-building-office class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $user->employer->company_name }}</span>
                                        @endif
                                        <span class="text-gray-500"><x-heroicon-o-trash class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $user->deleted_at?->format('M d, Y g:i A') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3 xl:shrink-0">
                                <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">
                                    @csrf
                                    @method('PATCH')

                                    <x-likeslocale.button type="submit" variant="accent">
                                        Restore User
                                    </x-likeslocale.button>
                                </form>

                                <form method="POST"
                                      action="{{ route('admin.users.force-delete', $user->id) }}"
                                      onsubmit="return confirm('Permanently delete {{ addslashes($user->name) }}? This cannot be undone and will be blocked if protected records exist.');">
                                    @csrf
                                    @method('DELETE')

                                    <x-likeslocale.button type="submit" variant="secondary">
                                        Permanently Delete
                                    </x-likeslocale.button>
                                </form>
                            </div>
                        </div>
                    </x-likeslocale.operation-row>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-layouts.portal>
