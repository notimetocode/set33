<p>
    This Privacy Policy describes what data the {{ config('app.name') }} service (“Service”, “we”)
    collects and processes, for what purposes, and on what terms. By using the Service, you agree
    to this policy.
</p>

<h2>1. Who we are</h2>
<p>
    {{ config('app.name') }} is an AI SEO analysis service based on data from connected sources
    (Google Analytics, Google Search Console, GitHub, and other integrations). The personal data
    controller is the owner of the {{ config('app.name') }} Service.
</p>
<p>
    For data processing questions, email
    <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
</p>

<h2>2. What data we collect</h2>
<h3>2.1. Account data</h3>
<ul>
    <li>name;</li>
    <li>email address;</li>
    <li>password hash (the password itself is never stored in plain text);</li>
    <li>technical sign-in details (for example, IP address and request time) as needed for security.</li>
</ul>

<h3>2.2. Connection and site data</h3>
<ul>
    <li>URLs and parameters of sites you add in the account;</li>
    <li>OAuth tokens and identifiers for Google and GitHub connections;</li>
    <li>email of the linked Google / GitHub account (if provided by the provider);</li>
    <li>metrics and events you request from Google Analytics and Search Console;</li>
    <li>GitHub repository and commit information related to your sites;</li>
    <li>events and AI reports you create in the Service.</li>
</ul>

<h3>2.3. Cookies and similar technologies</h3>
<p>
    We use necessary cookies for site and session operation.
    You can clear site data in your browser to change that.
</p>

<h2>3. How we use data</h2>
<ul>
    <li>creating and maintaining your account;</li>
    <li>connecting external services at your request;</li>
    <li>syncing metrics and generating AI reports;</li>
    <li>security and abuse prevention;</li>
    <li>communicating with you about the service;</li>
    <li>complying with legal requirements.</li>
</ul>
<p>
    We do not sell personal data to third parties.
</p>

<h2>4. Sharing with third parties</h2>
<p>
    Data may be shared only as needed to operate the Service:
</p>
<ul>
    <li>infrastructure providers (hosting, databases, job queues);</li>
    <li>Google and GitHub — within OAuth and APIs you connect yourself;</li>
    <li>AI providers — to generate reports from data you synced into the Service;</li>
    <li>public authorities — when required by law.</li>
</ul>
<p>
    Processing of Google data (Analytics, Search Console, and related APIs)
    follows Google policies and Google API Terms of Service. We request only the permissions
    required for Service features.
</p>

<h2>5. Storage and security</h2>
<p>
    We retain data while your account exists or while data is needed to provide services
    and meet obligations. Technical logs may be kept for a limited time. We take reasonable
    organizational and technical measures to protect data from unauthorized access.
</p>

<h2>6. Your rights</h2>
<p>You may:</p>
<ul>
    <li>request access to your data or a copy of it;</li>
    <li>correct inaccurate data in your profile or by contacting us;</li>
    <li>delete your account and related data (subject to lawful retention grounds);</li>
    <li>revoke the Service’s access to Google / GitHub in those account settings.</li>
</ul>
<p>
    For requests, email
    <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
</p>

<h2>7. Children</h2>
<p>
    The Service is not intended for anyone under 18. We do not knowingly collect children’s data.
</p>

<h2>8. Policy changes</h2>
<p>
    We may update this Policy. The current version is always available at
    <a href="{{ localized_route('public.privacy') }}">{{ localized_route('public.privacy') }}</a>.
    Material changes may also be noted on the site or in a notice.
</p>

<h2>9. Contact</h2>
<p>
    Privacy questions:
    <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
</p>
