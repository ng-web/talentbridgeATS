<x-layouts.portal :title="'Notifications'" heading="Notifications" subheading="Stay updated on important activity." :portalRole="auth()->user()?->hasRole('admin') ? 'admin' : (auth()->user()?->hasRole('employer') ? 'employer' : 'jobseeker')">
    <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-xl font-semibold text-gray-900">All Notifications</h3>
                <p class="mt-1 text-sm text-gray-500">Review recent activity across your account.</p>
            </div>

            @if(auth()->user()?->unreadNotifications->count())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')

                    <x-likeslocale.button type="submit" variant="secondary">
                        Mark All Read
                    </x-likeslocale.button>
                </form>
            @endif
        </div>

        @if($notifications->isEmpty())
            <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-6 text-center">
                <p class="text-gray-500">No notifications yet.</p>
            </div>
        @else
            <div class="mt-6 space-y-4">
                @foreach($notifications as $notification)
                    @php
                        $data = $notification->data;
                        $isUnread = $notification->read_at === null;
                    @endphp

                    <div class="rounded-2xl border px-5 py-4 {{ $isUnread ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-white' }}">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                            <div>
                                <p class="font-semibold text-gray-900">
                                    {{ $data['title'] ?? 'Notification' }}
                                </p>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ $data['message'] ?? '' }}
                                </p>
                                <p class="mt-2 text-xs text-gray-400">
                                    {{ $notification->created_at?->diffForHumans() }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                @if(!empty($data['url']))
                                    <a href="{{ $data['url'] }}">
                                        <x-likeslocale.button type="button" variant="secondary">
                                            View
                                        </x-likeslocale.button>
                                    </a>
                                @endif

                                @if($isUnread)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                        @csrf
                                        @method('PATCH')

                                        <x-likeslocale.button type="submit" variant="accent">
                                            Mark Read
                                        </x-likeslocale.button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-layouts.portal>