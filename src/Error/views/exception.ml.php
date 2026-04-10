<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - {{ get_class($e) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        {!! $css !!}

        /* Custom styles for tabs and request details */
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
        }

        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            padding: 0.5rem 1rem;
            position: relative;
            transition: color var(--transition-base);
        }

        .tab-btn.active {
            color: var(--accent);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1rem;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
            border-radius: 3px 3px 0 0;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.4s ease-out;
        }

        .layout-with-sidebar {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
        }

        .sidebar-tabs {
            width: 250px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            position: sticky;
            top: 2rem;
        }

        .sidebar-tab-btn {
            background: transparent;
            border: 1px solid transparent;
            border-radius: 8px;
            color: var(--text-secondary);
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-base);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-tab-btn:hover {
            background: var(--surface-tertiary);
        }

        .sidebar-tab-btn.active {
            background: var(--surface-secondary);
            border-color: var(--border);
            color: var(--accent);
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }

        .sidebar-content {
            flex-grow: 1;
            min-width: 0;
        }

        .inner-tab-content {
            display: none;
        }

        .inner-tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        @media (max-width: 768px) {
            .layout-with-sidebar {
                flex-direction: column;
            }

            .sidebar-tabs {
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
                position: static;
            }

            .sidebar-tab-btn {
                flex: 1 1 auto;
                justify-content: center;
            }
        }

        .request-card {
            background: var(--surface-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .request-card-header {
            background: var(--surface-tertiary);
            padding: 1rem 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
        }

        .request-card-body {
            padding: 1.5rem;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-subtle);
        }

        .data-table th {
            color: var(--text-muted);
            font-weight: 500;
            width: 30%;
            vertical-align: top;
        }

        .data-table td {
            color: var(--text-primary);
            word-break: break-all;
        }

        .data-table tr:last-child th,
        .data-table tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-header">
            <div class="error-header-content">
                <div class="error-icon-wrapper">
                    <div class="error-icon">⚡</div>
                    <div class="icon-glow"></div>
                </div>
                <div class="error-title-container">
                    <h1 class="error-title">{{ $e->getMessage() ?: 'An error occurred' }}</h1>
                    <div class="error-type">{{ get_class($e) }}</div>
                </div>
            </div>
        </div>

        <div class="error-content">
            @if($debug)
            <div class="error-meta">
                <div class="meta-item">
                    <div class="meta-label">Timestamp</div>
                    <div class="meta-value">{{ $timestamp }}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Error Code</div>
                    <div class="meta-value">{{ $e->getCode() }}</div>
                </div>
            </div>

            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab(this, 'tab-exception')">Exception</button>
                @if(isset($request))
                <button class="tab-btn" onclick="switchTab(this, 'tab-request')">Request Details</button>
                @endif
                @if(isset($session))
                <button class="tab-btn" onclick="switchTab(this, 'tab-session')">Session</button>
                @endif
            </div>

            <div id="tab-exception" class="tab-content active">
                <div class="layout-with-sidebar">
                    <div class="sidebar-tabs">
                        <button class="sidebar-tab-btn active" onclick="switchInnerTab(this, 'exc-overview')">Overview & Context</button>
                        <button class="sidebar-tab-btn" onclick="switchInnerTab(this, 'exc-stack')">Stack Trace</button>
                    </div>
                    <div class="sidebar-content">
                        <!-- Overview & Context -->
                        <div id="exc-overview" class="inner-tab-content active">
                            <div class="error-details">
                                <div class="detail-header">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span>Error Location</span>
                                </div>
                                <div class="error-location">
                                    <div class="location-item">
                                        <span class="location-label">File:</span>
                                        <span class="location-path">{{ $e->getFile() }}</span>
                                    </div>
                                    <div class="location-item">
                                        <span class="location-label">Line:</span>
                                        <span class="location-line">{{ $e->getLine() }}</span>
                                    </div>
                                </div>
                            </div>

                            @if($context)
                            <div class="section">
                                <div class="section-header">
                                    <div class="section-title">
                                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                        </svg>
                                        Code Context
                                    </div>
                                </div>
                                <div class="code-context">
                                    @foreach($context as $line)
                                    <div class="code-line {{ $line['isError'] ? 'highlight' : '' }}">
                                        <span class="line-number">{{ $line['number'] }}</span>
                                        <span class="line-content">{{ $line['content'] }}</span>
                                        @if($line['isError'])
                                        <span class="error-marker">← Error occurred here</span>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>

                        <!-- Stack Trace -->
                        <div id="exc-stack" class="inner-tab-content">
                            <div class="section" style="margin-top: 0;">
                                <div class="section-header">
                                    <div class="section-title">
                                        <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 6h16M4 12h16M4 18h16"></path>
                                        </svg>
                                        Stack Trace
                                    </div>
                                </div>
                                <div class="stack-trace" id="stackTrace" style="display: block;">
                                    @foreach($trace as $item)
                                    <div class="stack-item">
                                        <div class="stack-number">#{{ $item['index'] }}</div>
                                        <div class="stack-info">
                                            <div class="stack-function">{{ $item['call'] }}()</div>
                                            <div class="stack-file">{{ $item['location'] }}</div>
                                        </div>
                                    </div>
                                    @endforeach
                                    @if(count($trace) === 0)
                                    <div class="empty-state">No stack trace available</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if(isset($request))
            <div id="tab-request" class="tab-content">
                <div class="layout-with-sidebar">
                    <div class="sidebar-tabs">
                        <button class="sidebar-tab-btn active" onclick="switchInnerTab(this, 'req-general')">General</button>
                        <button class="sidebar-tab-btn" onclick="switchInnerTab(this, 'req-headers')">Headers</button>
                        <button class="sidebar-tab-btn" onclick="switchInnerTab(this, 'req-server')">Server Params</button>
                        <button class="sidebar-tab-btn" onclick="switchInnerTab(this, 'req-query')">Query Params</button>
                        <button class="sidebar-tab-btn" onclick="switchInnerTab(this, 'req-body')">Parsed Body</button>
                    </div>

                    <div class="sidebar-content">
                        <!-- Request Info -->
                        <div id="req-general" class="inner-tab-content active">
                            <div class="request-card">
                                <div class="request-card-header">General Information</div>
                                <div class="request-card-body">
                                    <table class="data-table">
                                        <tr>
                                            <th>Method</th>
                                            <td>{{ $request->getMethod() }}</td>
                                        </tr>
                                        <tr>
                                            <th>URI</th>
                                            <td>{{ (string) $request->getUri() }}</td>
                                        </tr>
                                        <tr>
                                            <th>Protocol Version</th>
                                            <td>{{ $request->getProtocolVersion() }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Headers -->
                        <div id="req-headers" class="inner-tab-content">
                            <div class="request-card">
                                <div class="request-card-header">Headers</div>
                                <div class="request-card-body">
                                    <?php $headersList = $request->getHeaders(); ?>
                                    <table class="data-table">
                                        @foreach($headersList as $name => $values)
                                        <tr>
                                            <th>{{ ucwords(str_replace('-', ' ', $name)) }}</th>
                                            <td>{{ implode(', ', $values) }}</td>
                                        </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Server Params -->
                        <div id="req-server" class="inner-tab-content">
                            <div class="request-card">
                                <div class="request-card-header">Server Parameters</div>
                                <div class="request-card-body">
                                    <?php $serverParamsList = $request->getServerParams(); ?>
                                    <table class="data-table">
                                        @foreach($serverParamsList as $key => $value)
                                        @if(!is_array($value) && !is_object($value))
                                        @if(preg_match('/^(HTTP_|SERVER_|REQUEST_|REMOTE_|DOCUMENT_)/i', $key))
                                        <tr>
                                            <th>{{ $key }}</th>
                                            <td>{{ $value }}</td>
                                        </tr>
                                        @endif
                                        @endif
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Query Params -->
                        <div id="req-query" class="inner-tab-content">
                            <div class="request-card">
                                <div class="request-card-header">Query Parameters</div>
                                <div class="request-card-body">
                                    @if(!$request->getQueryParams())
                                    <span style="color: var(--text-muted)">No query parameters provided.</span>
                                    @else
                                    <?php $queryParamsList = $request->getQueryParams(); ?>
                                    <table class="data-table">
                                        @foreach($queryParamsList as $key => $value)
                                        <tr>
                                            <th>{{ $key }}</th>
                                            <td>{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                                        </tr>
                                        @endforeach
                                    </table>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Parsed Body -->
                        <div id="req-body" class="inner-tab-content">
                            <div class="request-card">
                                <div class="request-card-header">Parsed Body</div>
                                <div class="request-card-body">
                                    @if(!$request->getParsedBody())
                                    <span style="color: var(--text-muted)">No parsed body provided.</span>
                                    @else
                                    <?php $parsedBodyList = (array)$request->getParsedBody(); ?>
                                    <table class="data-table">
                                        @foreach($parsedBodyList as $key => $value)
                                        <tr>
                                            <th>{{ $key }}</th>
                                            <td>{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                                        </tr>
                                        @endforeach
                                    </table>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($session))
            <div id="tab-session" class="tab-content">
                <div class="layout-with-sidebar">
                    <div class="sidebar-tabs">
                        <button class="sidebar-tab-btn active" onclick="switchInnerTab(this, 'sess-data')">Session Data</button>
                        <button class="sidebar-tab-btn" onclick="switchInnerTab(this, 'sess-metadata')">Metadata</button>
                    </div>
                    <div class="sidebar-content">
                        <!-- Session Data -->
                        <div id="sess-data" class="inner-tab-content active">
                            <div class="request-card">
                                <div class="request-card-header">Session Attributes</div>
                                <div class="request-card-body">
                                    <?php $sessionAttrsList = $session->all(); ?>
                                    @if(empty($sessionAttrsList))
                                    <span style="color: var(--text-muted);">No session attributes available.</span>
                                    @else
                                    <table class="data-table">
                                        @foreach($sessionAttrsList as $key => $value)
                                        <tr>
                                            <th>{{ $key }}</th>
                                            <td>{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                                        </tr>
                                        @endforeach
                                    </table>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Session Metadata -->
                        <div id="sess-metadata" class="inner-tab-content">
                            <div class="request-card">
                                <div class="request-card-header">Metadata & State</div>
                                <div class="request-card-body">
                                    <table class="data-table">
                                        <tr>
                                            <th>Session ID</th>
                                            <td>{{ $session->getId() ?: 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Started</th>
                                            <td>{{ $session->isStarted() ? 'Yes' : 'No' }}</td>
                                        </tr>
                                        <tr>
                                            <th>IP Address</th>
                                            <td>{{ $session->getIpAddress() ?: 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>User Agent</th>
                                            <td>{{ $session->getUserAgent() ?: 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>User ID</th>
                                            <td>{{ $session->getUserId() ?: 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <th>CSRF Token</th>
                                            <td>{{ $session->has('_token') ? $session->token() : 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @else
            <div class="error-message-container">
                <div class="error-illustration">
                    <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="100" cy="100" r="80" fill="#fee2e2" opacity="0.3" />
                        <circle cx="100" cy="100" r="60" fill="#fecaca" opacity="0.4" />
                        <path d="M100 60 L100 110" stroke="#b91c1c" stroke-width="8" stroke-linecap="round" />
                        <circle cx="100" cy="130" r="6" fill="#b91c1c" />
                    </svg>
                </div>
                <h2 class="error-message-title">Oops! Something went wrong</h2>
                <p class="error-message-text">An internal server error occurred.</p>
                <div class="error-actions">
                    <button class="action-btn primary" onclick="window.location.reload()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Try Again
                    </button>
                    <button class="action-btn secondary" onclick="window.history.back()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Go Back
                    </button>
                </div>
            </div>
            @endif
        </div>

        <div class="error-footer">
            <div class="footer-content">
                <div class="footer-logo">
                    <span class="logo-text">MonkeysLegion</span>
                    <span class="logo-version">Framework</span>
                </div>
                <div class="footer-links">
                    <a href="https://monkeyslegion.com/docs" target="_blank">Documentation</a>
                    <a href="https://github.com/MonkeysCloud/MonkeysLegion-Skeleton" target="_blank">GitHub</a>
                </div>
            </div>
        </div>
    </div>

    @if($debug)
    <script>
        function switchTab(btn, tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        function switchInnerTab(btn, tabId) {
            const container = btn.closest('.layout-with-sidebar');
            container.querySelectorAll('.sidebar-tab-btn').forEach(b => b.classList.remove('active'));
            container.querySelectorAll('.inner-tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        // Copy code functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('copy-btn')) {
                const code = e.target.closest('.code-line').querySelector('.line-content').textContent;
                navigator.clipboard.writeText(code).then(() => {
                    e.target.textContent = '✓';
                    setTimeout(() => e.target.textContent = '📋', 2000);
                });
            }
        });
    </script>
    @endif
</body>

</html>