<p>
    These Terms of Service (“Terms”) govern access to and use of the
    {{ config('app.name') }} service (“Service”, “we”).
    By registering for or using the Service, you confirm that you have read and accept
    these Terms and the
    <a href="{{ localized_route('public.privacy') }}">Privacy Policy</a>.
</p>

<h2>1. Service description</h2>
<p>
    {{ config('app.name') }} provides tools to connect data sources
    (including Google Analytics, Google Search Console, GitHub), sync metrics,
    and generate AI reports for sites. Features may expand or change.
</p>

<h2>2. Account</h2>
<ul>
    <li>you must provide accurate information when registering;</li>
    <li>you are responsible for keeping your password safe and for activity in the account;</li>
    <li>one account is for one person or organization; sharing access with others is at your risk;</li>
    <li>we may restrict or block an account for Terms violations or suspected abuse.</li>
</ul>

<h2>3. Acceptable use</h2>
<p>You must not:</p>
<ul>
    <li>use the Service unlawfully or to bypass third-party API limits;</li>
    <li>attempt unauthorized access to systems, other users’ data, or infrastructure;</li>
    <li>overload the Service with automated requests beyond reasonable limits;</li>
    <li>transmit malware or content that infringes third-party rights;</li>
    <li>present Service reports as guaranteed legal, financial, or investment advice.</li>
</ul>

<h2>4. Third-party integrations</h2>
<p>
    By connecting Google, GitHub, or other services, you confirm you have the right
    to grant the Service access to the relevant data and agree to those providers’ terms.
    We do not control third-party APIs and are not responsible for their outages,
    policy changes, or access revocation.
</p>
<p>
    You may revoke access at any time in Google / GitHub settings
    and/or in the Service account.
</p>

<h2>5. AI reports and content</h2>
<p>
    Reports are generated automatically from available data and AI models.
    Results are informational: inaccuracies, incompleteness, or outdated conclusions
    are possible. Final decisions about your site, marketing, and business are yours.
</p>

<h2>6. Plans and payment</h2>
<p>
    Some features may be offered under plans shown on the site
    (including the Pricing section). Payment, renewal, and refund terms
    are provided when you purchase a paid plan. We may change plans
    with prior notice for active subscriptions where applicable.
</p>

<h2>7. Intellectual property</h2>
<p>
    The Service, its design, code, and marks belong to us or our licensors.
    You receive a limited, non-exclusive, non-transferable license to use the Service
    under these Terms. Your site data and materials you create remain yours;
    you grant us a license to process them to provide the services.
</p>

<h2>8. Disclaimer of warranties</h2>
<p>
    The Service is provided “as is” and “as available”. We do not guarantee
    uninterrupted operation, compatibility with all sites and APIs, or any specific
    SEO or business results.
</p>

<h2>9. Limitation of liability</h2>
<p>
    To the maximum extent permitted by applicable law, we are not liable for
    indirect, incidental, or punitive damages, lost profits, data loss, or reputational
    harm arising from use of the Service. Aggregate liability for any claims related
    to the Service is limited to the amount you actually paid us in the last 12 months
    (or zero if no fee was charged).
</p>

<h2>10. Termination</h2>
<p>
    You may stop using the Service and request account deletion.
    We may suspend or end access for Terms violations, legal requirements,
    or if the Service is discontinued.
</p>

<h2>11. Changes to the Terms</h2>
<p>
    We may update these Terms. The current version is published at
    <a href="{{ localized_route('public.terms') }}">{{ localized_route('public.terms') }}</a>.
    Continued use after changes are published means you accept the new version,
    unless the law requires otherwise.
</p>

<h2>12. Contact</h2>
<p>
    Questions about the Terms:
    <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
</p>
