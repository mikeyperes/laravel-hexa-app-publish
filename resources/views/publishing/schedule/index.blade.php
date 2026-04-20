{{-- Schedule — modern minimalist redesign --}}
@extends('layouts.app')
@section('title', 'Schedule')
@section('header', 'Schedule')

@push('styles')
<style>
    /* Minimalist FullCalendar theme */
    .fc {
        --fc-border-color: #f1f5f9;
        --fc-today-bg-color: #fafafa;
        --fc-neutral-bg-color: transparent;
        --fc-page-bg-color: transparent;
        --fc-event-bg-color: #111827;
        --fc-event-border-color: #111827;
        --fc-event-text-color: #fff;
        font-family: inherit;
    }
    .fc-theme-standard .fc-scrollgrid,
    .fc-theme-standard th,
    .fc-theme-standard td {
        border-color: var(--fc-border-color) !important;
    }
    .fc-theme-standard .fc-scrollgrid { border-width: 0 !important; }
    .fc-col-header-cell {
        background: transparent !important;
        padding: 12px 8px !important;
    }
    .fc-col-header-cell-cushion {
        color: #9ca3af !important;
        font-size: 11px !important;
        font-weight: 500 !important;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        text-decoration: none !important;
    }
    .fc-daygrid-day-number {
        padding: 10px 12px !important;
        font-size: 13px !important;
        font-weight: 400 !important;
        color: #374151 !important;
        text-decoration: none !important;
    }
    .fc-day-other .fc-daygrid-day-number { color: #d1d5db !important; }
    .fc-day-today { background: #fafafa !important; }
    .fc-day-today .fc-daygrid-day-number {
        color: #111827 !important;
        font-weight: 600 !important;
    }
    .fc .fc-toolbar {
        margin-bottom: 24px !important;
        gap: 12px;
    }
    .fc .fc-toolbar-title {
        font-size: 17px !important;
        font-weight: 500 !important;
        color: #111827 !important;
        letter-spacing: -0.01em;
    }
    .fc .fc-button {
        background: #fff !important;
        color: #4b5563 !important;
        border: 1px solid #e5e7eb !important;
        font-size: 12px !important;
        padding: 6px 12px !important;
        font-weight: 500 !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
        box-shadow: none !important;
        border-radius: 6px !important;
        height: auto !important;
        transition: all 0.15s ease;
    }
    .fc .fc-button:hover:not(:disabled) {
        background: #f9fafb !important;
        color: #111827 !important;
        border-color: #d1d5db !important;
    }
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background: #111827 !important;
        color: #fff !important;
        border-color: #111827 !important;
    }
    .fc .fc-button-group > .fc-button {
        border-radius: 0 !important;
    }
    .fc .fc-button-group > .fc-button:first-child { border-radius: 6px 0 0 6px !important; }
    .fc .fc-button-group > .fc-button:last-child { border-radius: 0 6px 6px 0 !important; }
    .fc .fc-icon { font-size: 14px !important; }
    .fc-daygrid-event {
        border-radius: 4px !important;
        padding: 3px 8px !important;
        font-size: 11px !important;
        font-weight: 500 !important;
        border: none !important;
        margin: 1px 4px !important;
    }
    .fc-daygrid-event:hover { opacity: 0.85; }
    .fc-daygrid-day-frame { min-height: 110px !important; }
    .fc-daygrid-day-events { min-height: 0 !important; }
    .fc-timegrid-slot { height: 40px !important; }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto" x-data="scheduleManager()" x-init="init()">

    {{-- Stats row --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 px-5 py-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Connected Sites</p>
            <p class="text-2xl font-light text-gray-900 mt-1">{{ $sites->count() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 px-5 py-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled Posts</p>
            <p class="text-2xl font-light text-gray-900 mt-1" x-text="scheduledPosts.length"></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 px-5 py-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Upcoming This Week</p>
            <p class="text-2xl font-light text-gray-900 mt-1" x-text="upcomingThisWeek()"></p>
        </div>
    </div>

    {{-- Info banner — only if no sites --}}
    @if($sites->isEmpty())
    <div class="bg-amber-50 border border-amber-100 rounded-xl px-5 py-4 mb-6 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M5 20h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 13.25A2 2 0 005 20z"/></svg>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-amber-900">No WordPress sites connected</p>
            <p class="text-xs text-amber-800 mt-0.5">Connect sites in the <a href="{{ route('publish.sites.index') }}" class="underline hover:no-underline">Sites</a> section to start fetching scheduled posts.</p>
        </div>
    </div>
    @endif

    {{-- Main card --}}
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">

        {{-- Header strip: tabs + actions --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 gap-4 flex-wrap">
            <div class="flex items-center gap-1 bg-gray-50 rounded-lg p-1">
                <button @click="activeTab = 'calendar'"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="activeTab === 'calendar' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Calendar
                </button>
                <button @click="activeTab = 'list'"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors"
                        :class="activeTab === 'list' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    List
                </button>
            </div>

            <div class="flex items-center gap-3">
                <span x-show="lastFetchedAt" x-cloak class="text-xs text-gray-400" x-text="'Last fetched ' + lastFetchedAt"></span>
                <button @click="fetchScheduled()" :disabled="fetching || {{ $sites->count() }} === 0"
                        class="inline-flex items-center gap-2 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    <svg x-show="!fetching" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h5M20 20v-5h-5M4 9a8 8 0 0114-4.5M20 15a8 8 0 01-14 4.5"/></svg>
                    <svg x-show="fetching" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="fetching ? 'Fetching' : 'Refresh'"></span>
                </button>
            </div>
        </div>

        {{-- Fetch result --}}
        <template x-if="fetchResult">
            <div class="px-5 py-2 text-xs border-b border-gray-100"
                 :class="fetchSuccess ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                 x-text="fetchResult"></div>
        </template>

        {{-- Calendar --}}
        <div x-show="activeTab === 'calendar'" x-cloak class="px-5 pt-5 pb-5">
            <template x-if="scheduledPosts.length === 0">
                <div class="absolute inset-x-0 bottom-5 pointer-events-none flex justify-center z-10">
                    <div class="pointer-events-auto inline-flex items-center gap-2 text-xs text-gray-400 bg-white border border-gray-100 rounded-full px-3 py-1.5 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        No scheduled posts yet — click Refresh to load from connected sites
                    </div>
                </div>
            </template>
            <div id="schedule-calendar"></div>
        </div>

        {{-- List --}}
        <div x-show="activeTab === 'list'" x-cloak>
            <template x-if="scheduledPosts.length === 0">
                <div class="px-5 py-16 text-center">
                    <svg class="w-10 h-10 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="mt-3 text-sm text-gray-500">No scheduled posts</p>
                    <p class="mt-1 text-xs text-gray-400">Click Refresh above to fetch from connected WordPress sites.</p>
                </div>
            </template>
            <template x-if="scheduledPosts.length > 0">
                <div>
                    <template x-for="(group, date) in groupedPosts()" :key="date">
                        <div>
                            <div class="px-5 py-2.5 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-700" x-text="formatGroupDate(date)"></span>
                                <span class="text-xs text-gray-400" x-text="group.length + ' post' + (group.length === 1 ? '' : 's')"></span>
                            </div>
                            <template x-for="post in group" :key="post.wp_post_id + '-' + post.site_id">
                                <a :href="post.link" target="_blank"
                                   class="block px-5 py-3.5 border-b border-gray-100 hover:bg-gray-50 transition-colors group">
                                    <div class="flex items-start gap-4">
                                        <div class="shrink-0 text-center pt-0.5">
                                            <p class="text-[10px] font-medium text-gray-400 uppercase tracking-wider" x-text="formatTime(post.date)"></p>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 break-words group-hover:text-gray-700" x-text="post.title || 'Untitled'"></p>
                                            <div class="mt-1 flex items-center gap-3 text-xs text-gray-500">
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                                    <span x-text="post.site_name"></span>
                                                </span>
                                                <span class="text-gray-300">·</span>
                                                <span class="inline-flex items-center" x-text="post.status"></span>
                                            </div>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-300 group-hover:text-gray-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>
<script>
let calendarInstance = null;

function scheduleManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
    return {
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'calendar',
        scheduledPosts: [],
        fetching: false, fetchResult: '', fetchSuccess: false,
        lastFetchedAt: '',

        init() {
            this.$watch('activeTab', (val) => {
                const url = new URL(window.location);
                url.searchParams.set('tab', val);
                history.replaceState(null, '', url);
                if (val === 'calendar') this.$nextTick(() => this.initCalendar());
            });
            this.$nextTick(() => { if (this.activeTab === 'calendar') this.initCalendar(); });
        },

        initCalendar() {
            const el = document.getElementById('schedule-calendar');
            if (!el || calendarInstance) return;
            calendarInstance = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
                buttonText: { today: 'Today', month: 'Month', week: 'Week', list: 'List' },
                firstDay: 1,
                dayMaxEvents: 3,
                height: 'auto',
                events: this.calendarEvents(),
                eventDisplay: 'block',
                eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
            });
            calendarInstance.render();
        },

        calendarEvents() {
            return this.scheduledPosts.map(p => ({
                title: p.title || 'Untitled',
                start: p.date,
                url: p.link,
                backgroundColor: '#111827',
                borderColor: '#111827',
                textColor: '#fff',
                extendedProps: { site: p.site_name },
            }));
        },

        refreshCalendar() {
            if (!calendarInstance) return;
            calendarInstance.removeAllEvents();
            this.calendarEvents().forEach(e => calendarInstance.addEvent(e));
        },

        upcomingThisWeek() {
            const now = new Date();
            const end = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
            return this.scheduledPosts.filter(p => {
                const d = new Date(p.date);
                return d >= now && d <= end;
            }).length;
        },

        groupedPosts() {
            const sorted = [...this.scheduledPosts].sort((a, b) => new Date(a.date) - new Date(b.date));
            const groups = {};
            sorted.forEach(p => {
                const d = new Date(p.date);
                const key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                groups[key] = groups[key] || [];
                groups[key].push(p);
            });
            return groups;
        },

        formatGroupDate(key) {
            const d = new Date(key + 'T00:00:00');
            const today = new Date();
            const tomorrow = new Date(today.getTime() + 86400000);
            if (d.toDateString() === today.toDateString()) return 'Today · ' + d.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });
            if (d.toDateString() === tomorrow.toDateString()) return 'Tomorrow · ' + d.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });
            return d.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
        },

        formatTime(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        },

        async fetchScheduled() {
            this.fetching = true; this.fetchResult = '';
            try {
                const r = await fetch('{{ route("publish.schedule.fetch") }}', { method: 'POST', headers });
                const d = await r.json();
                this.fetchSuccess = d.success;
                this.fetchResult = d.success ? (d.count || 0) + ' scheduled post' + ((d.count === 1) ? '' : 's') + ' found across connected sites.' : (d.message || 'Failed to fetch posts.');
                if (d.success) {
                    this.scheduledPosts = d.scheduled || [];
                    this.refreshCalendar();
                    this.lastFetchedAt = new Date().toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
                }
            } catch(e) {
                this.fetchSuccess = false;
                this.fetchResult = 'Error: ' + e.message;
            } finally {
                this.fetching = false;
            }
        },
    };
}
</script>
@endpush
