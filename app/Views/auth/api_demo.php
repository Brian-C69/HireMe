<?php $base = defined('BASE_URL') ? BASE_URL : ''; ?>
<section class="py-5">
    <div class="container" style="max-width: 720px;">
        <h1 class="h3 mb-3">Auth API Demo</h1>
        <p class="text-muted">
            Enter credentials to call <code>POST <?= htmlspecialchars($base) ?>/api/auth/login.php</code> and
            inspect the JSON response returned by the API.
        </p>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="demo-email">Email</label>
                    <input class="form-control" id="demo-email" type="email" value="candidate@example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="demo-password">Password</label>
                    <input class="form-control" id="demo-password" type="password" value="password123">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="demo-role">Role (optional)</label>
                    <select class="form-select" id="demo-role">
                        <option value="">(auto-detect)</option>
                        <option value="candidate">Candidate</option>
                        <option value="employer">Employer</option>
                        <option value="recruiter">Recruiter</option>
                    </select>
                </div>
                <button class="btn btn-primary w-100" id="demo-btn" type="button">Call Login API</button>
            </div>
        </div>

        <h2 class="h5">Response</h2>
        <pre id="demo-output" class="bg-light border rounded p-3">No request yet.</pre>
    </div>
</section>
<script>
(() => {
    const base = <?= json_encode($base) ?>;
    const btn = document.getElementById('demo-btn');
    const output = document.getElementById('demo-output');
    if (!btn || !output) {
        return;
    }

    btn.addEventListener('click', async () => {
        output.textContent = 'Calling...';
        const email = document.getElementById('demo-email').value.trim();
        const password = document.getElementById('demo-password').value;
        const role = document.getElementById('demo-role').value;

        const body = JSON.stringify({ email, password, role });
        const url = (base || '') + '/api/auth/login.php';

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body,
            });
            const json = await res.json();
            output.textContent = JSON.stringify(json, null, 2);
        } catch (err) {
            output.textContent = 'Request error: ' + err.message;
        }
    });
})();
</script>
