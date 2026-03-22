{{-- Schedule --}}
@extends('layouts.app')
@section('title', 'Schedule')
@section('header', 'Schedule - All Scheduled Posts')

@section('content')
<div class="space-y-4" x-data="scheduleManager()">

    {{-- Tab navigation --}}
    <div class="flex items-center gap-1 border-b border-gray-200">
        <button @click="activeTab = 'calendar'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="activeTab === 'calendar' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
            Calendar
        </button>
        <button @click="activeTab = 'list'" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="activeTab === 'list' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
            List
        </button>
    </div>

    {{-- Info banner --}}
    <div class="bg-blue-50 rounded-lg border border-blue-200 p-3 text-sm text-blue-700">
        Connect WordPress sites in the <a href="{{ route('publish.sites.index') }}" class="underline font-medium">Sites</a> section to view scheduled posts here.
    </div>

    {{-- Fetch button --}}
    <div class="flex items-center gap-3">
        <button @click="fetchScheduled()" :disabled="fetching" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 inline-flex items-center gap-2">
            <svg x-show="fetching" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            <span x-text="fetching ? 'Fetching...' : 'Fetch Scheduled Posts'"></span>
        </button>
        <span x-show="fetchResult" x-cloak class="text-sm" :class="fetchSuccess ? 'text-green-600' : 'text-red-600'" x-text="fetchResult"></span>
    </div>

    {{-- Calendar tab --}}
    <div x-show="activeTab === 'calendar'" x-cloak>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div id="schedule-calendar" style="min-height: 500px;"></div>
        </div>
    </div>

    {{-- List tab --}}
    <div x-show="activeTab === 'list'" x-cloak>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <template x-if="scheduledPosts.length === 0">
                <div class="p-8 text-center text-gray-500 text-sm">
                    No scheduled posts found. Click "Fetch Scheduled Posts" to check connected WordPress sites.
                </div>
            </template>
            <template x-if="scheduledPosts.length > 0">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Title</th>
                            <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Site</th>
                            <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Scheduled Date</th>
                            <th class="px-5 py-2 text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="post in scheduledPosts" :key="post.wp_post_id + '-' + post.site_id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-2 font-medium break-words">
                                    <a :href="post.link" target="_blank" class="text-blue-600 hover:text-blue-800" x-text="post.title"></a>
                                </td>
                                <td class="px-5 py-2 text-xs text-gray-500" x-text="post.site_name"></td>
                                <td class="px-5 py-2 text-xs text-gray-500" x-text="post.date ? new Date(post.date).toLocaleString() : '--'"></td>
                                <td class="px-5 py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800" x-text="post.status"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- FullCalendar CDN --}}
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
        init() {
            // Update URL when tab changes
            this.$watch('activeTab', (val) => {
                const url = new URL(window.location);
                url.searchParams.set('tab', val);
                history.replaceState(null, '', url);
                if (val === 'calendar') {
                    this.$nextTick(() => this.initCalendar());
                }
            });
            // Init calendar on load if calendar tab active
            this.$nextTick(() => {
                if (this.activeTab === 'calendar') this.initCalendar();
            });
        },
        initCalendar() {
            const el = document.getElementById('schedule-calendar');
            if (!el || calendarInstance) return;
            calendarInstance = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
                events: this.scheduledPosts.map(p => ({
                    title: p.title + ' (' + p.site_name + ')',
                    start: p.date,
                    url: p.link,
                    color: '#3b82f6',
                })),
                height: 'auto',
            });
            calendarInstance.render();
        },
        async fetchScheduled() {
            this.fetching = true; this.fetchResult = '';
            try {
                const r = await fetch('{{ route("publish.schedule.fetch") }}', { method: 'POST', headers });
                const d = await r.json();
                this.fetchSuccess = d.success;
                this.fetchResult = d.success ? d.count + ' scheduled post(s) found.' : 'Failed to fetch posts.';
                if (d.success) {
                    this.scheduledPosts = d.scheduled || [];
                    // Update calendar
                    if (calendarInstance) {
                        calendarInstance.removeAllEvents();
                        this.scheduledPosts.forEach(p => {
                            calendarInstance.addEvent({
                                title: p.title + ' (' + p.site_name + ')',
                                start: p.date,
                                url: p.link,
                                color: '#3b82f6',
                            });
                        });
                    }
                }
            } catch(e) { this.fetchSuccess = false; this.fetchResult = 'Error: ' + e.message; }
            this.fetching = false;
        }
    };
}
</script>
@endpush
