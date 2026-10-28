<x-app-layout>
    <x-slot name="pageTitle">LinkHop - {{ config('hop.control_name') }}</x-slot>

    <x-page-header title="The admin panel moved" subtitle="Moderation and settings now live in the HOP control center." />

    <section class="glass-card space-y-3 text-sm text-muted">
        <p>
            Users, links, bio pages, abuse reports, IP bans, the domain blacklist, SEO and the job queue are managed in
            <strong class="text-ink">{{ config('hop.control_name') }}</strong>, which talks to this server through the signed HOP admin API.
        </p>
        <p>
            This installation has no control center address yet. Set <code>HOP_CONTROL_URL</code> in <code>.env</code> to send
            <code>/admin</code> there, or use the <code>/api/hop/*</code> API and the artisan commands
            (<code>abuse:scan</code>, <code>hop:bootstrap</code>, …) directly.
        </p>
    </section>
</x-app-layout>
