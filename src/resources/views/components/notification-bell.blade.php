@php
    $user = auth()->user();
    $unreadCount = $user?->unreadNotifications()->count() ?? 0;
    $latestNotifications = $user?->notifications()->latest()->take(5)->get() ?? collect();
@endphp

@if($user)
    <div
        class="relative"
        x-data="{
            open: false,
            ready: false,
            panelStyle: 'display:none;',
            positionPanel() {
                const button = this.$refs.bellButton;
                const panelWidth = Math.min(420, window.innerWidth - 32);
                const buttonRect = button.getBoundingClientRect();

                let left = buttonRect.right - panelWidth;
                const minLeft = 16;
                const maxLeft = window.innerWidth - panelWidth - 16;

                if (left < minLeft) left = minLeft;
                if (left > maxLeft) left = maxLeft;

                const top = buttonRect.bottom + 12;

                this.panelStyle = `position:fixed; top:${top}px; left:${left}px; width:${panelWidth}px; max-width:calc(100vw - 32px); z-index:100;`;
            },
            openPanel() {
                this.positionPanel();
                this.ready = true;
                this.open = true;
            },
            closePanel() {
                this.open = false;
                this.ready = false;
            },
            togglePanel() {
                if (this.open) {
                    this.closePanel();
                    return;
                }

                this.openPanel();
            }
        }"
        @click.outside="closePanel()"
        @resize.window="if (open) positionPanel()"
    >
        <button
            type="button"
            x-ref="bellButton"
            @click="togglePanel()"
            class="relative inline-flex items-center justify-center w-10 h-10 rounded-full border border-gray-200 bg-white hover:bg-gray-50 transition"
            aria-label="Notifications"
        >
            <x-heroicon-o-bell class="w-5 h-5 text-gray-600" />

            @if($unreadCount > 0)
                <span class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold">
                    {{ $unreadCount }}
                </span>
            @endif
        </button>

        <div
            x-show="open && ready"
            x-transition
            x-cloak
            class="origin-top-right rounded-3xl border border-gray-200 bg-white shadow-2xl overflow-hidden"
            :style="panelStyle"
        >
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                    <p class="text-xs text-gray-500">
                        {{ $unreadCount }} unread
                    </p>
                </div>

                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        @method('PATCH')

                        <button
                            type="submit"
                            class="text-xs font-medium text-[#6f4cb2] hover:underline"
                        >
                            Mark all read
                        </button>
                    </form>
                @endif
            </div>

            @if($latestNotifications->isEmpty())
                <div class="px-5 py-8 text-center">
                    <p class="text-sm text-gray-500">No notifications yet.</p>
                </div>
            @else
                <div class="max-h-[24rem] overflow-y-auto">
                    @foreach($latestNotifications as $notification)
                        @php
                            $data = $notification->data ?? [];
                            $isUnread = $notification->read_at === null;
                        @endphp

                        <div class="border-b border-gray-100 last:border-b-0 {{ $isUnread ? 'bg-blue-50/60' : 'bg-white' }}">
                            <div class="px-5 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 pr-3">
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $data['title'] ?? 'Notification' }}
                                        </p>

                                        <p class="mt-1 text-sm text-gray-600 break-words">
                                            {{ $data['message'] ?? '' }}
                                        </p>

                                        <p class="mt-2 text-xs text-gray-400">
                                            {{ $notification->created_at?->diffForHumans() }}
                                        </p>
                                    </div>

                                    @if($isUnread)
                                        <span class="mt-1 inline-block w-2.5 h-2.5 rounded-full bg-blue-500 shrink-0"></span>
                                    @endif
                                </div>

                                <div class="mt-3 flex items-center gap-2">
                                    @if(!empty($data['url']))
                                        <a
                                            href="{{ $data['url'] }}"
                                            class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            View
                                        </a>
                                    @endif

                                    @if($isUnread)
                                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                            @csrf
                                            @method('PATCH')

                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-xl bg-[#6f4cb2] px-3 py-1.5 text-xs font-medium text-white hover:opacity-90"
                                            >
                                                Mark read
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="px-5 py-4 border-t border-gray-100 bg-gray-50">
                <a
                    href="{{ route('notifications.index') }}"
                    class="block w-full text-center rounded-2xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    View all notifications
                </a>
            </div>
        </div>
    </div>
@endif