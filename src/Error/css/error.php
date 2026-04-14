:root {
    --primary: #0d1b2a;
    --primary-light: #1b263b;
    --secondary: #415a77;
    --accent: #e0a458;
    --accent-light: #f1c587;
    --accent-dark: #d4924a;
    --surface: #ffffff;
    --surface-secondary: #f7f9fc;
    --surface-tertiary: #edf2f7;
    --error: #b91c1c;
    --error-subtle: #fee2e2;
    --success: #16a34a;
    --text-primary: #1a202c;
    --text-secondary: #4a5568;
    --text-muted: #718096;
    --border: #e2e8f0;
    --border-subtle: #edf2f4;

    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.05), 0 5px 15px rgba(0, 0, 0, 0.03);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.08);
    --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.1);
    
    --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
    --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html,
body {
    height: 100%;
    width: 100%;
    overflow-x: hidden;
    overflow-y: auto;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--secondary) 100%);
    color: var(--text-primary);
    line-height: 1.6;
    min-height: 100vh;
    padding: 0;
    margin: 0;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.error-container {
    background: var(--surface);
    width: 100%;
    min-height: 100vh;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-xl);
    position: relative;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Enhanced Header */
.error-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 4rem 3rem;
    position: relative;
    overflow: hidden;
}

.error-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(224, 164, 88, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(224, 164, 88, 0.1) 0%, transparent 50%);
    z-index: 1;
    animation: pulse 8s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.error-header::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.02' fill-rule='evenodd'/%3E%3C/svg%3E");
    opacity: 0.3;
    z-index: 0;
}

.error-header-content {
    position: relative;
    z-index: 2;
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    align-items: flex-start;
    gap: 2rem;
    width: 100%;
    padding: 0 2rem;
    animation: slideDown 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.error-icon-wrapper {
    position: relative;
    flex-shrink: 0;
}

.error-icon {
    width: 90px;
    height: 90px;
    background: var(--accent);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 42px;
    flex-shrink: 0;
    box-shadow: 0 10px 30px rgba(224, 164, 88, 0.3);
    transform: rotate(-5deg);
    transition: all var(--transition-base);
    margin-top: 0.5rem;
    position: relative;
    z-index: 2;
    animation: bounce 2s ease-in-out infinite;
}

@keyframes bounce {
    0%, 100% {
        transform: rotate(-5deg) translateY(0);
    }
    50% {
        transform: rotate(-5deg) translateY(-5px);
    }
}

.error-icon:hover {
    transform: rotate(0deg) scale(1.05);
    box-shadow: 0 15px 40px rgba(224, 164, 88, 0.4);
}

.icon-glow {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 120px;
    height: 120px;
    background: radial-gradient(circle, rgba(224, 164, 88, 0.4) 0%, transparent 70%);
    border-radius: 50%;
    animation: glow 3s ease-in-out infinite;
    z-index: 1;
}

@keyframes glow {
    0%, 100% {
        opacity: 0.5;
        transform: translate(-50%, -50%) scale(1);
    }
    50% {
        opacity: 0.8;
        transform: translate(-50%, -50%) scale(1.1);
    }
}

.error-title-container {
    flex: 1;
    max-width: calc(100% - 140px);
    overflow-wrap: break-word;
    word-break: break-word;
}

.error-title {
    font-size: 2.75rem;
    font-weight: 800;
    margin-bottom: 1rem;
    line-height: 1.2;
    flex: 1;
    letter-spacing: -0.03em;
    color: white;
    overflow-wrap: break-word;
    word-wrap: break-word;
    hyphens: auto;
    margin-right: 1rem;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.error-type {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--accent);
    background: rgba(224, 164, 88, 0.15);
    padding: 0.6rem 1.4rem;
    border-radius: 50px;
    display: inline-block;
    border: 1.5px solid rgba(224, 164, 88, 0.4);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    transition: all var(--transition-base);
}

.error-type:hover {
    background: rgba(224, 164, 88, 0.25);
    border-color: rgba(224, 164, 88, 0.6);
    transform: translateY(-2px);
}

/* Content Area */
.error-content {
    flex: 1;
    padding: 3rem;
    max-width: 1500px;
    margin: 0 auto;
    width: 100%;
    overflow-y: auto;
    animation: fadeIn 0.8s ease-out 0.2s both;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Meta Information */
.error-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.meta-item {
    background: var(--surface-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all var(--transition-base);
}

.meta-item:hover {
    border-color: var(--accent);
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.meta-label {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
}

.meta-value {
    font-size: 1.125rem;
    color: var(--text-primary);
    font-weight: 600;
    font-family: 'JetBrains Mono', monospace;
}

/* Error Details */
.error-details {
    background: linear-gradient(135deg, var(--error-subtle) 0%, #fef2f2 100%);
    border: 2px solid rgba(185, 28, 28, 0.15);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 3rem;
    position: relative;
    box-shadow: var(--shadow-md);
    transition: all var(--transition-base);
}

.error-details:hover {
    box-shadow: var(--shadow-lg);
    border-color: rgba(185, 28, 28, 0.25);
}

.error-details::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: linear-gradient(180deg, var(--error) 0%, #dc2626 100%);
    border-radius: 6px 0 0 6px;
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    color: var(--error);
    font-weight: 600;
    font-size: 1.125rem;
}

.detail-icon {
    width: 24px;
    height: 24px;
    color: var(--error);
}

.error-location {
    font-family: 'JetBrains Mono', 'SF Mono', 'Monaco', monospace;
    font-size: 0.95rem;
    color: var(--text-secondary);
}

.location-item {
    display: flex;
    align-items: baseline;
    gap: 0.75rem;
    padding: 0.5rem 0;
}

.location-label {
    color: var(--text-muted);
    font-weight: 600;
    min-width: 50px;
}

.location-path,
.location-line {
    color: var(--text-primary);
    font-weight: 500;
    word-break: break-all;
}

.location-line {
    color: var(--error);
    font-weight: 700;
}

/* Section Styling */
.section {
    margin-bottom: 3rem;
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.section-title {
    font-size: 1.375rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-icon {
    width: 24px;
    height: 24px;
    color: var(--accent);
}

.toggle-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--surface-secondary);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all var(--transition-base);
}

.toggle-btn:hover {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.toggle-icon {
    width: 16px;
    height: 16px;
    transition: transform var(--transition-base);
}

/* Code Context */
.code-context {
    background: var(--primary);
    border-radius: 20px;
    padding: 2.5rem 2rem;
    overflow-x: auto;
    border: 2px solid var(--primary-light);
    box-shadow: var(--shadow-lg);
    position: relative;
}

.code-context::before {
    content: '';
    position: absolute;
    top: 16px;
    left: 16px;
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #ff5f56;
    box-shadow: 22px 0 0 #ffbd2e, 44px 0 0 #27c93f;
}

.code-line {
    font-family: 'JetBrains Mono', 'SF Mono', 'Monaco', monospace;
    font-size: 0.95rem;
    line-height: 1.8;
    color: #94a3b8;
    display: flex;
    align-items: flex-start;
    padding: 0.4rem 0;
    transition: all var(--transition-fast);
    min-width: max-content;
}

.code-line:hover {
    background: rgba(255, 255, 255, 0.02);
}

.line-number {
    color: #64748b;
    width: 4ch;
    text-align: right;
    margin-right: 2rem;
    user-select: none;
    flex-shrink: 0;
    opacity: 0.5;
    font-weight: 500;
}

.line-content {
    flex: 1;
    white-space: pre;
}

.code-line.highlight {
    background: rgba(224, 164, 88, 0.15);
    border-left: 4px solid var(--accent);
    padding-left: 1.5rem;
    margin-left: -1.5rem;
    color: #ffffff;
    border-radius: 0 8px 8px 0;
    position: relative;
    padding-right: 2rem;
    animation: highlight 1s ease-out;
}

@keyframes highlight {
    0% {
        background: rgba(224, 164, 88, 0.3);
    }
    100% {
        background: rgba(224, 164, 88, 0.15);
    }
}

.code-line.highlight .line-number {
    color: var(--accent);
    font-weight: 700;
    opacity: 1;
}

.error-marker {
    margin-left: 1rem;
    color: var(--accent);
    font-size: 0.875rem;
    font-weight: 600;
    animation: pulse-marker 2s ease-in-out infinite;
}

@keyframes pulse-marker {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}

/* Stack Trace */
.stack-trace {
    background: var(--surface-secondary);
    border: 2px solid var(--border);
    border-radius: 20px;
    box-shadow: var(--shadow-md);
    max-height: none;
    margin-bottom: 2rem;
}

.stack-item {
    padding: 1.75rem 2rem;
    border-bottom: 1px solid var(--border-subtle);
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    transition: all var(--transition-base);
}

.stack-item:last-child {
    border-bottom: none;
}

.stack-item:hover {
    background: var(--surface-tertiary);
    transform: translateX(8px);
    box-shadow: inset 4px 0 0 var(--accent);
}

.stack-number {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--accent);
    min-width: 40px;
    text-align: center;
    background: rgba(224, 164, 88, 0.1);
    border-radius: 8px;
    padding: 0.25rem 0.5rem;
}

.stack-info {
    flex: 1;
}

.stack-function {
    font-family: 'JetBrains Mono', monospace;
    color: var(--accent);
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.stack-file {
    font-family: 'JetBrains Mono', monospace;
    color: var(--text-secondary);
    font-size: 0.875rem;
    word-break: break-all;
}

/* Production Error Message */
.error-message-container {
    text-align: center;
    max-width: 600px;
    margin: 2rem auto;
    padding: 3rem 2rem;
}

.error-illustration {
    width: 200px;
    height: 200px;
    margin: 0 auto 2rem;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

.error-message-title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.error-message-text {
    font-size: 1.125rem;
    color: var(--text-secondary);
    line-height: 1.7;
    margin-bottom: 2rem;
}

/* Action Buttons */
.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 2rem;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.75rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all var(--transition-base);
    border: 2px solid transparent;
}

.action-btn svg {
    width: 20px;
    height: 20px;
}

.action-btn.primary {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}

.action-btn.primary:hover {
    background: var(--accent-dark);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(224, 164, 88, 0.3);
}

.action-btn.secondary {
    background: white;
    color: var(--text-primary);
    border-color: var(--border);
}

.action-btn.secondary:hover {
    border-color: var(--accent);
    color: var(--accent);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.error-support {
    color: var(--text-muted);
    font-size: 0.95rem;
}

.error-support a {
    color: var(--accent);
    text-decoration: none;
    font-weight: 600;
    transition: color var(--transition-fast);
}

.error-support a:hover {
    color: var(--accent-dark);
    text-decoration: underline;
}

/* Footer */
.error-footer {
    background: var(--surface-secondary);
    border-top: 1px solid var(--border);
    padding: 2rem 3rem;
    margin-top: auto;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.footer-logo {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.logo-text {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

.logo-version {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.footer-links {
    display: flex;
    gap: 2rem;
}

.footer-links a {
    color: var(--text-secondary);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all var(--transition-fast);
    position: relative;
}

.footer-links a::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--accent);
    transition: width var(--transition-base);
}

.footer-links a:hover {
    color: var(--accent);
}

.footer-links a:hover::after {
    width: 100%;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 3rem;
    color: var(--text-muted);
    font-style: italic;
    background: var(--surface-tertiary);
    border-radius: 20px;
    border: 2px dashed var(--border);
    font-size: 1.125rem;
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}

::-webkit-scrollbar-track {
    background: var(--surface-tertiary);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
    transition: background var(--transition-base);
    border: 2px solid var(--surface-tertiary);
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-light);
}

* {
    scrollbar-width: thin;
    scrollbar-color: var(--secondary) var(--surface-tertiary);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .error-header-content,
    .error-content {
        max-width: 100%;
    }
}

@media (max-width: 768px) {
    .error-header {
        padding: 3rem 1.5rem;
    }

    .error-header-content {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 1.5rem;
    }

    .error-title-container {
        max-width: 100%;
    }

    .error-title {
        font-size: 2rem;
    }

    .error-icon {
        width: 80px;
        height: 80px;
        font-size: 36px;
    }

    .error-content {
        padding: 2rem 1.5rem;
    }

    .error-meta {
        grid-template-columns: 1fr;
    }

    .error-actions {
        flex-direction: column;
    }

    .action-btn {
        width: 100%;
        justify-content: center;
    }

    .footer-content {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }

    .footer-links {
        flex-direction: column;
        gap: 1rem;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .stack-item {
        flex-direction: column;
        gap: 1rem;
    }

    .stack-number {
        align-self: flex-start;
    }
}

@media (max-width: 576px) {
    .error-header {
        padding: 2.5rem 1.25rem;
    }

    .error-title {
        font-size: 1.75rem;
    }

    .error-content {
        padding: 1.5rem 1.25rem;
    }

    .code-context {
        padding: 2rem 1rem;
    }

    .error-footer {
        padding: 1.5rem 1.25rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    :root {
        --surface: #0f1419;
        --surface-secondary: #1a1f2e;
        --surface-tertiary: #252b3b;
        --text-primary: #e2e8f0;
        --text-secondary: #a0aec0;
        --text-muted: #718096;
        --border: #2d3748;
        --border-subtle: #374151;
    }

    .error-details {
        background: linear-gradient(135deg, rgba(185, 28, 28, 0.15) 0%, rgba(185, 28, 28, 0.1) 100%);
    }

    .code-line {
        color: #cbd5e0;
    }

    .action-btn.secondary {
        background: var(--surface-secondary);
        color: var(--text-primary);
    }
}