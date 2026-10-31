<x-info-page title="Terms" lead="The rules for short links on LinkHop. They add to the terms that cover every HOP app.">
    <div class="mb-6 rounded-2xl border border-sky-500/30 bg-sky-50/50 p-4 text-sm text-sky-900 dark:border-sky-500/20 dark:bg-sky-950/20 dark:text-sky-200">
        <p class="font-semibold">{{ __('Master HOP Agreement') }}</p>
        <p class="mt-1">{!! __('These rules govern LinkHop and operate alongside the Master HOP Terms of Service at <a href="https://hel.ink/terms" class="underline font-medium">hel.ink/terms</a>. Both documents are legally binding on all users.') !!}</p>
    </div>

    <h2>{{ __('Not allowed') }}</h2>
    <ul>
        <li>{{ __('Links to phishing, malware, scams, or anything illegal where you or the visitor are.') }}</li>
        <li>{{ __('Hiding where a link goes in order to mislead people.') }}</li>
        <li>{{ __('Spam, or using LinkHop to dodge another service\'s blocks.') }}</li>
        <li>{{ __('Impersonating a person, brand or official body with a slug.') }}</li>
    </ul>
    <h2>{{ __('What we do') }}</h2>
    <p>{!! __('Links are checked when they are made and scanned every hour. A link that breaks these rules is disabled, and an account that keeps breaking them is suspended. Report a link with the <a href=":url">report form</a>.', ['url' => route('report.create')]) !!}</p>
    <h2>{{ __('Guest links') }}</h2>
    <p>{{ __('Links made without an account are deleted after :idle days without a click, and after :max days at most.', ['idle' => $idleDays, 'max' => $maxDays]) }}</p>
    <h2>{{ __('No warranty') }}</h2>
    <p>{{ __('LinkHop is free and provided as is. We do our best to keep links working, but cannot promise that every link stays available forever.') }}</p>
    <p>{!! __('The full terms for every HOP app are at <a href="https://hel.ink/terms">hel.ink/terms</a>. For questions, contact <!--email_off--><a href="mailto:support@hel.ink">support@hel.ink</a><!--/email_off-->.') !!}</p>
</x-info-page>
