<?php
/** @var int $step */
/** @var list<array{label:string,required:string,actual:string,ok:bool}> $checks */
/** @var bool $checksReady */
/** @var array<string,mixed>|null $license */
/** @var string|null $licenseApi */
/** @var string|null $licenseDocs */
/** @var string $domain */
/** @var array<string,string> $oldAdmin */
/** @var int $pendingTableCount */
/** @var array<string,mixed>|null $receipt */
/** @var array{type:string,message:string}|null $flash */
$stageLabels = [
    1 => ['Welcome & checks', 'Verify this server'],
    2 => ['Database & schema', 'Prepare your data'],
    3 => ['Admin & email', 'Set up access'],
    4 => ['All set', 'Next steps'],
];
$stageNames = [1 => 'Welcome & checks', 2 => 'Database & schema', 3 => 'Admin & email', 4 => 'All set'];
$licenseApi = (string) ($licenseApi ?? 'https://manager.pmhserver.name.ng/api.php');
$licenseDocs = (string) ($licenseDocs ?? 'https://manager.pmhserver.name.ng/api-docs.php');
$oldAdmin = is_array($oldAdmin ?? null) ? $oldAdmin : [];
$schemaTables = is_array($schemaTables ?? null) ? $schemaTables : [];
$schemaCatalog = [
    'users' => ['Administrator accounts', 'Unique email and username · role/status index'],
    'login_attempts' => ['Failed sign-in counters', 'Identifier/time and IP/time indexes'],
    'login_history' => ['Sign-in audit history', 'User/time and IP/time indexes'],
    'ip_blocks' => ['Temporary security blocks', 'Block expiry index'],
    'audit_logs' => ['System activity audit', 'Event/time and user/time indexes'],
    'notification_outbox' => ['Email delivery queue', 'Status/retry, event/time, recipient/time indexes'],
    'transactions' => ['Transaction records', 'Unique reference/provider reference · status/time indexes'],
    'shop_products' => ['Shop catalog', 'SKU and public availability/stock indexes'],
    'shop_orders' => ['Shop orders', 'Payment, customer, and reservation indexes'],
    'shop_order_items' => ['Order line items', 'Product-price snapshots · order history'],
    'password_reset_tokens' => ['Password reset tokens', 'Unique token hash · user/expiry index'],
    'system_settings' => ['Site and security settings', 'Primary key on setting name'],
    'teams' => ['Tournament teams', 'Unique zone/team name · status and group indexes'],
    'venues' => ['Match venues', 'Zone and venue capacity directory'],
    'fixtures' => ['Fixtures and scores', 'Team, venue, status, and kick-off indexes'],
    'registrations' => ['Public registrations', 'Review status · applicant and date indexes'],
];
?>
<section class="installer-wrap">
    <div class="installer-heading">
        <div>
            <div class="eyebrow"><span class="eyebrow-line"></span>YOUTH UNITY CUP · INSTALLATION</div>
            <?php if ($step === 1): ?>
                <h1>Welcome to <em>Youth Unity Cup</em></h1>
                <p class="lede">A simple, secure setup for the tournament platform. We’ll check your server, prepare the database, and create your first administrator.</p>
            <?php elseif ($step === 2): ?>
                <h1>Connect the <em>database</em></h1>
                <p class="lede">Point the installer at an empty MySQL database. We’ll verify access and create the platform tables without dropping anything.</p>
            <?php elseif ($step === 3): ?>
                <h1>Set up your <em>administrator</em></h1>
                <p class="lede">Create the first secure sign-in and configure email delivery for important account, security, and transaction activity.</p>
            <?php else: ?>
                <h1>You’re ready to <em>kick off.</em></h1>
                <p class="lede">Youth Unity Cup is installed. Sign in to the admin area to finish your site setup and confirm notifications.</p>
            <?php endif; ?>
        </div>
        <div class="setup-stamp" aria-label="Setup stage">
            <span>STAGE</span><strong><?= sprintf('%02d', $step) ?></strong><small>OF 04</small>
        </div>
    </div>

    <nav class="stepper" aria-label="Installation stages">
        <?php foreach ($stageLabels as $number => [$label, $description]): ?>
            <?php $state = $step === $number ? 'current' : ($step > $number ? 'complete' : 'upcoming'); ?>
            <div class="stepper-item <?= yuc_e($state) ?>" <?= $step === $number ? 'aria-current="step"' : '' ?>>
                <span class="stepper-index"><?= $state === 'complete' ? '✓' : sprintf('%02d', $number) ?></span>
                <span class="stepper-copy"><strong><?= yuc_e($label) ?></strong><small><?= yuc_e($description) ?></small></span>
            </div>
        <?php endforeach; ?>
    </nav>

    <?php if (is_array($flash)): ?>
        <div class="alert alert-<?= yuc_e($flash['type']) ?>" role="status" aria-live="polite">
            <span class="alert-icon" aria-hidden="true"><?= $flash['type'] === 'error' ? '!' : '✓' ?></span>
            <p><?= yuc_e($flash['message']) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <div class="installer-columns">
            <section class="panel requirements-panel" aria-labelledby="requirements-title">
                <div class="panel-topline">
                    <div><span class="panel-kicker">01 / SYSTEM CHECK</span><h2 id="requirements-title">Server requirements</h2></div>
                    <span class="readiness-pill <?= $checksReady ? 'is-ready' : 'is-warning' ?>"><span></span><?= $checksReady ? 'READY TO CONTINUE' : 'ACTION REQUIRED' ?></span>
                </div>
                <p class="panel-intro">The installer needs PHP 8.1 or newer, MySQL support, and secure outbound HTTPS access.</p>
                <ul class="requirement-list">
                    <?php foreach ($checks as $check): ?>
                        <li class="requirement-row <?= $check['ok'] ? 'requirement-ok' : 'requirement-missing' ?>">
                            <span class="requirement-icon" aria-hidden="true"><?= $check['ok'] ? '✓' : '!' ?></span>
                            <span class="requirement-detail">
                                <strong><?= yuc_e($check['label']) ?></strong>
                                <small>Required: <?= yuc_e($check['required']) ?> <span class="requirement-separator">·</span> Detected: <?= yuc_e($check['actual']) ?></small>
                            </span>
                            <span class="requirement-state"><?= $check['ok'] ? 'Ready' : 'Missing' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="requirements-note">
                    <span class="note-icon" aria-hidden="true">i</span>
                    <p>After setup, point your web server document root to the <code>public/</code> folder. Keep <code>config/</code> and <code>storage/</code> outside the public web root.</p>
                </div>
            </section>

            <aside class="panel license-panel" aria-labelledby="verification-title">
                <div class="license-orbit" aria-hidden="true"><span>Y</span><i></i><i></i><i></i></div>
                <div class="panel-kicker">PROJECT ACTIVATION</div>
                <h2 id="verification-title">Verify your license</h2>
                <p class="panel-intro">To continue, validate the project key against the domain where this installation will run.</p>
                <div class="api-reference">
                    <span class="api-reference-icon" aria-hidden="true">↗</span>
                    <span><small>VERIFICATION SERVICE</small><strong>PMH License Manager</strong></span>
                    <a href="<?= yuc_e($licenseDocs) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open the license verification API documentation">API docs</a>
                </div>
                <div class="domain-line"><span>Current domain</span><strong><?= yuc_e($domain !== '' ? $domain : 'Could not detect domain') ?></strong></div>
                <form method="post" action="/install/license" class="form-stack">
                    <?= yuc_csrf_field() ?>
                    <label for="license-key">License key <span class="required-mark">*</span></label>
                    <div class="input-wrap input-with-icon">
                        <span class="input-icon" aria-hidden="true">⌑</span>
                        <input type="password" id="license-key" name="license_key" autocomplete="off" required maxlength="512" placeholder="Enter your project key" <?= !$checksReady ? 'disabled' : '' ?>>
                    </div>
                    <p class="privacy-note">When submitted, your key and current domain are sent from this server to the verification service over HTTPS. The key is stored in protected server-side configuration after setup.</p>
                    <button class="button button-primary button-full" type="submit" <?= !$checksReady ? 'disabled' : '' ?>>Verify key &amp; continue <span aria-hidden="true">→</span></button>
                </form>
                <div class="secure-caption"><span class="lock-mini" aria-hidden="true">⌑</span> Your key is never placed in browser-side JavaScript.</div>
            </aside>
        </div>
        <div class="below-note"><span class="soft-rule"></span><span>Setup usually takes about 3 minutes</span><span class="soft-rule"></span></div>

    <?php elseif ($step === 2): ?>
        <section class="panel stage-panel" aria-labelledby="database-title">
            <div class="panel-topline">
                <div><span class="panel-kicker">02 / STORAGE</span><h2 id="database-title">Database connection</h2></div>
                <span class="readiness-pill is-ready"><span></span>LICENSE VERIFIED</span>
            </div>
            <p class="panel-intro stage-copy">Use the database details supplied by your hosting provider. Create an empty MySQL database first; the installer will create the tables and indexes.</p>
            <form method="post" action="/install/database" class="form-stack form-grid">
                <?= yuc_csrf_field() ?>
                <div class="field-group">
                    <label for="db-host">Database host <span class="required-mark">*</span></label>
                    <input id="db-host" name="db_host" type="text" required maxlength="253" autocomplete="off" value="localhost" placeholder="localhost">
                    <small>Often <code>localhost</code> or a host name from your provider.</small>
                </div>
                <div class="field-group field-small">
                    <label for="db-port">Port</label>
                    <input id="db-port" name="db_port" type="number" min="1" max="65535" required value="3306" inputmode="numeric">
                    <small>Default MySQL port is 3306.</small>
                </div>
                <div class="field-group">
                    <label for="db-name">Database name <span class="required-mark">*</span></label>
                    <input id="db-name" name="db_name" type="text" required minlength="1" maxlength="64" autocomplete="off" placeholder="youth_unity_cup">
                </div>
                <div class="field-group">
                    <label for="db-username">Database username <span class="required-mark">*</span></label>
                    <input id="db-username" name="db_username" type="text" required maxlength="190" autocomplete="username" placeholder="Your database user">
                </div>
                <div class="field-group field-full">
                    <label for="db-password">Database password <span class="required-mark">*</span></label>
                    <input id="db-password" name="db_password" type="password" required maxlength="1024" autocomplete="new-password" placeholder="Your database password">
                    <small>Credentials are written to a protected local configuration file outside the public web root.</small>
                </div>
                <div class="form-actions field-full">
                    <a class="button button-quiet" href="/install">← Back</a>
                    <button class="button button-primary" type="submit">Connect &amp; install schema <span aria-hidden="true">→</span></button>
                </div>
            </form>
            <div class="schema-preview">
                <span class="schema-preview-icon" aria-hidden="true">▤</span>
                <span><strong>Schema preview</strong><small>Teams · Venues · Fixtures · Registrations · Admin users · Sign-in history · Email outbox · Transactions · Password-reset tokens · Settings</small></span>
                <span class="schema-version">MYSQL / INNODB</span>
            </div>
        </section>

    <?php elseif ($step === 3): ?>
        <section class="panel stage-panel" aria-labelledby="admin-setup-title">
            <div class="panel-topline">
                <div><span class="panel-kicker">03 / ADMINISTRATION</span><h2 id="admin-setup-title">Create your first admin</h2></div>
                <span class="readiness-pill is-ready"><span></span><?= (int) ($pendingTableCount ?? 0) ?> TABLES PREPARED</span>
            </div>
            <p class="panel-intro stage-copy">Choose a strong password you can remember. Your credentials are never echoed back after submission.</p>
            <details class="schema-details" open>
                <summary><span>Schema successfully installed</span><small><?= (int) ($pendingTableCount ?? 0) ?> tables · indexes ready</small></summary>
                <ul class="schema-catalog">
                    <?php foreach ($schemaTables as $tableName): ?>
                        <?php if (isset($schemaCatalog[$tableName])): ?>
                            <li><code><?= yuc_e($tableName) ?></code><span><strong><?= yuc_e($schemaCatalog[$tableName][0]) ?></strong><small><?= yuc_e($schemaCatalog[$tableName][1]) ?></small></span></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </details>
            <form method="post" action="/install/admin" class="form-stack">
                <?= yuc_csrf_field() ?>
                <div class="form-grid admin-fields">
                    <div class="field-group field-full">
                        <label for="full-name">Full name <span class="required-mark">*</span></label>
                        <input id="full-name" name="full_name" type="text" required maxlength="120" autocomplete="name" value="<?= yuc_e($oldAdmin['full_name'] ?? '') ?>" placeholder="Tournament administrator">
                    </div>
                    <div class="field-group">
                        <label for="admin-email">Admin email <span class="required-mark">*</span></label>
                        <input id="admin-email" name="admin_email" type="email" required maxlength="190" autocomplete="email" value="<?= yuc_e($oldAdmin['admin_email'] ?? '') ?>" placeholder="admin@example.com">
                    </div>
                    <div class="field-group">
                        <label for="admin-username">Username <span class="required-mark">*</span></label>
                        <input id="admin-username" name="admin_username" type="text" required minlength="3" maxlength="60" autocomplete="username" value="<?= yuc_e($oldAdmin['admin_username'] ?? '') ?>" placeholder="cup-admin">
                    </div>
                    <div class="field-group">
                        <label for="admin-password">Password <span class="required-mark">*</span></label>
                        <input id="admin-password" name="admin_password" type="password" required minlength="12" maxlength="1024" autocomplete="new-password" placeholder="At least 12 characters">
                        <small>Passwords are hashed with PHP’s password hashing API.</small>
                    </div>
                    <div class="field-group">
                        <label for="admin-password-confirmation">Confirm password <span class="required-mark">*</span></label>
                        <input id="admin-password-confirmation" name="admin_password_confirmation" type="password" required minlength="12" maxlength="1024" autocomplete="new-password" placeholder="Enter it once more">
                    </div>
                </div>

                <div class="mail-setup-card">
                    <div class="mail-setup-heading">
                        <span class="mail-icon" aria-hidden="true">✉</span>
                        <div><h3>Email notifications</h3><p>Login, security, installation, and transaction notifications are saved to an outbox and delivered through SMTP.</p></div>
                        <span class="optional-pill">IMPORTANT</span>
                    </div>
                    <details class="mail-details" open>
                        <summary>Configure SMTP now <span>Recommended before go-live</span></summary>
                        <div class="form-grid smtp-grid">
                            <div class="field-group">
                                <label for="smtp-host">SMTP host</label>
                                <input id="smtp-host" name="smtp_host" type="text" maxlength="253" autocomplete="off" value="<?= yuc_e($oldAdmin['smtp_host'] ?? '') ?>" placeholder="smtp.example.com">
                                <small>Leave blank to queue emails until configured.</small>
                            </div>
                            <div class="field-group field-small">
                                <label for="smtp-port">Port</label>
                                <input id="smtp-port" name="smtp_port" type="number" min="1" max="65535" value="<?= yuc_e($oldAdmin['smtp_port'] ?? '587') ?>" inputmode="numeric">
                            </div>
                            <div class="field-group">
                                <label for="smtp-encryption">Encryption</label>
                                <select id="smtp-encryption" name="smtp_encryption">
                                    <?php foreach (['tls' => 'STARTTLS (recommended)', 'ssl' => 'SSL/TLS', 'none' => 'None (not recommended)'] as $value => $label): ?>
                                        <option value="<?= yuc_e($value) ?>" <?= ($oldAdmin['smtp_encryption'] ?? 'tls') === $value ? 'selected' : '' ?>><?= yuc_e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="smtp-username">SMTP username</label>
                                <input id="smtp-username" name="smtp_username" type="text" maxlength="190" autocomplete="off" value="<?= yuc_e($oldAdmin['smtp_username'] ?? '') ?>" placeholder="Optional">
                            </div>
                            <div class="field-group">
                                <label for="smtp-password">SMTP password</label>
                                <input id="smtp-password" name="smtp_password" type="password" maxlength="1024" autocomplete="new-password" placeholder="Optional">
                            </div>
                            <div class="field-group">
                                <label for="mail-from-email">Sender email</label>
                                <input id="mail-from-email" name="mail_from_email" type="email" maxlength="190" value="<?= yuc_e($oldAdmin['mail_from_email'] ?? ($oldAdmin['admin_email'] ?? '')) ?>" placeholder="noreply@example.com">
                            </div>
                            <div class="field-group">
                                <label for="mail-from-name">Sender name</label>
                                <input id="mail-from-name" name="mail_from_name" type="text" maxlength="120" value="<?= yuc_e($oldAdmin['mail_from_name'] ?? 'Youth Unity Cup') ?>" placeholder="Youth Unity Cup">
                            </div>
                            <div class="field-group field-full">
                                <label for="notifications-to">Notification recipient</label>
                                <input id="notifications-to" name="notifications_to" type="email" maxlength="190" value="<?= yuc_e($oldAdmin['notifications_to'] ?? ($oldAdmin['admin_email'] ?? '')) ?>" placeholder="admin@example.com">
                                <small>Security and system alerts are copied to this inbox. Transaction alerts can also go to the affected user.</small>
                            </div>
                        </div>
                    </details>
                    <p class="mail-storage-note"><span aria-hidden="true">⌑</span> SMTP credentials are stored in a permission-restricted configuration file outside the public web root. Without SMTP, messages remain visible in the outbox for retry.</p>
                </div>

                <div class="form-actions">
                    <a class="button button-quiet" href="/install">← Back</a>
                    <button class="button button-primary" type="submit">Create admin &amp; finish <span aria-hidden="true">→</span></button>
                </div>
            </form>
        </section>

    <?php else: ?>
        <?php $receipt = is_array($receipt ?? null) ? $receipt : []; ?>
        <section class="completion-panel" aria-labelledby="complete-title">
            <div class="success-art" aria-hidden="true">
                <div class="success-ball"><span>Y</span></div>
                <div class="success-spark spark-one">✦</div><div class="success-spark spark-two">✦</div><div class="success-spark spark-three">✦</div>
                <div class="success-ground"></div>
            </div>
            <div class="completion-copy">
                <div class="success-label"><span></span> INSTALLATION COMPLETE</div>
                <h2 id="complete-title">Congratulations,<br><em><?= yuc_e($receipt['name'] ?? 'Administrator') ?>.</em></h2>
                <p>Youth Unity Cup is now installed and your first administrator account is ready to use.</p>
                <div class="admin-credentials">
                    <div><small>ADMIN USERNAME</small><strong><?= yuc_e($receipt['username'] ?? '') ?></strong></div>
                    <div><small>ADMIN EMAIL</small><strong><?= yuc_e($receipt['email'] ?? '') ?></strong></div>
                    <span class="credential-lock" title="Password is not displayed">⌑</span>
                </div>
                <div class="email-status <?= !empty($receipt['emailConfigured']) ? 'email-on' : 'email-off' ?>">
                    <span aria-hidden="true"><?= !empty($receipt['emailConfigured']) ? '✓' : '!' ?></span>
                    <p><strong><?= !empty($receipt['emailConfigured']) ? 'SMTP is configured.' : 'Email delivery needs attention.' ?></strong> <?= !empty($receipt['emailConfigured']) ? 'Sign in and send a test notification before launch.' : 'Notifications are safely queued, but set up SMTP in your hosting settings before launch.' ?></p>
                </div>
                <a class="button button-primary button-login" href="/admin/login">Continue to admin sign in <span aria-hidden="true">→</span></a>
            </div>
        </section>

        <section class="next-steps" aria-labelledby="next-steps-title">
            <div class="next-steps-heading"><span class="panel-kicker">YOUR FIRST ADMIN SESSION</span><h2 id="next-steps-title">A strong start in five steps</h2></div>
            <ol class="next-steps-list">
                <li><span>01</span><div><strong>Sign in with your new account</strong><small>Use the username or email and the password you chose during setup.</small></div></li>
                <li><span>02</span><div><strong>Confirm email delivery</strong><small>Open the dashboard and send a test email. Review queued or failed messages.</small></div></li>
                <li><span>03</span><div><strong>Set site basics</strong><small>Confirm the site title, timezone, contact details, and match-day information.</small></div></li>
                <li><span>04</span><div><strong>Review security activity</strong><small>Check sign-in history and keep the administrator password private.</small></div></li>
                <li><span>05</span><div><strong>Prepare tournament content</strong><small>Review teams, venues, fixtures, results, registration, and public-facing links.</small></div></li>
            </ol>
        </section>
    <?php endif; ?>
</section>
