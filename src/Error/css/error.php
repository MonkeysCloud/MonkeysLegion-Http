:root {
    --primary: #0d1b2a;
    --primary-light: #1b263b;
    --secondary: #415a77;
    --accent: #e0a458;
    --accent-light: #f1c587;
    --surface: #ffffff;
    --surface-secondary: #f7f9fc;
    --surface-tertiary: #edf2f7;
    --error: #b91c1c;
    --error-subtle: #fee2e2;
    --text-primary: #1a202c;
    --text-secondary: #4a5568;
    --text-muted: #718096;
    --border: #e2e8f0;
    --border-subtle: #edf2f4;

    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.05), 0 5px 15px rgba(0, 0, 0, 0.03);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.08);
    --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.1);
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

.error-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 5rem 3rem;
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
        radial-gradient(circle at 20% 30%, rgba(224, 164, 88, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(224, 164, 88, 0.08) 0%, transparent 50%);
    z-index: 1;
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
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    align-items: flex-start;
    gap: 2.5rem;
    width: 100%;
    padding: 0 2rem;
}

.error-icon {
    width: 90px;
    height: 90px;
    background: var(--accent);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 42px;
    flex-shrink: 0;
    box-shadow: 0 10px 20px rgba(224, 164, 88, 0.2);
    transform: rotate(-5deg);
    transition: transform 0.3s ease;
    margin-top: 0.5rem;
}

.error-icon:hover {
    transform: rotate(0deg) scale(1.05);
}

.error-title-container {
    flex: 1;
    max-width: calc(100% - 120px);
    overflow-wrap: break-word;
    word-break: break-word;
}

.error-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.2;
    flex: 1;
    letter-spacing: -0.02em;
    color: white;
    overflow-wrap: break-word;
    word-wrap: break-word;
    hyphens: auto;
    margin-right: 1rem;
}

.error-type {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--accent);
    background: rgba(224, 164, 88, 0.1);
    padding: 0.5rem 1.2rem;
    border-radius: 30px;
    display: inline-block;
    border: 1px solid rgba(224, 164, 88, 0.3);
    backdrop-filter: blur(5px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.error-content {
    flex: 1;
    padding: 3rem;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    overflow-y: auto;
}

.error-details {
    background: var(--error-subtle);
    border: 1px solid rgba(185, 28, 28, 0.1);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 3rem;
    position: relative;
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.3s ease;
}

.error-details:hover {
    box-shadow: var(--shadow-md);
}

.error-details::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: var(--error);
    border-radius: 6px 0 0 6px;
}

.error-location {
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-size: 0.95rem;
    color: var(--text-secondary);
    font-weight: 400;
    line-height: 1.8;
}

.error-location strong {
    color: var(--text-primary);
    font-weight: 600;
}

.section {
    margin-bottom: 3rem;
    animation: fadeIn 0.5s ease-out;
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

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title::before {
    content: '';
    width: 5px;
    height: 24px;
    background: var(--accent);
    border-radius: 3px;
}

.code-context {
    background: var(--primary);
    border-radius: 16px;
    padding: 2rem;
    overflow-x: auto;
    border: 1px solid var(--primary-light);
    box-shadow: var(--shadow-lg);
    position: relative;
}

.code-context::before {
    content: '';
    position: absolute;
    top: 12px;
    left: 12px;
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #ff5f56;
    box-shadow: 20px 0 0 #ffbd2e, 40px 0 0 #27c93f;
}

.code-line {
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-size: 0.95rem;
    line-height: 1.7;
    color: #94a3b8;
    display: flex;
    padding: 0.35rem 0;
}

.line-number {
    color: #64748b;
    width: 4ch;
    text-align: right;
    margin-right: 1.5rem;
    user-select: none;
    flex-shrink: 0;
    opacity: 0.5;
}

.line-content {
    flex: 1;
    overflow-x: auto;
    white-space: pre;
}

.code-line.highlight {
    background: rgba(224, 164, 88, 0.1);
    border-left: 3px solid var(--accent);
    padding-left: 1rem;
    margin-left: -1rem;
    color: #ffffff;
    border-radius: 0 6px 6px 0;
    position: relative;
}

.code-line.highlight::before {
    content: '';
    position: absolute;
    left: -5px;
    top: 0;
    bottom: 0;
    width: 8px;
    background: var(--accent);
    border-radius: 4px;
    transform: scaleY(0.7);
}

.code-line.highlight .line-number {
    color: var(--accent);
    font-weight: 600;
    opacity: 1;
}

.stack-trace {
    background: var(--surface-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    max-height: 500px;
    overflow-y: auto;
    box-shadow: var(--shadow-sm);
    max-height: none;
    box-shadow: var(--shadow-sm);
    margin-bottom: 2rem;
}

.stack-item {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-subtle);
    font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
    font-size: 0.875rem;
    color: var(--text-secondary);
    transition: all 0.2s ease;
}

.stack-item:last-child {
    border-bottom: none;
}

.stack-item:hover {
    background: var(--surface-tertiary);
    transform: translateX(5px);
}

.stack-file {
    color: var(--text-primary);
    font-weight: 500;
    margin-bottom: 0.5rem;
    word-break: break-all;
    word-wrap: break-word;
}

.stack-function {
    color: var(--accent);
    font-weight: 600;
    position: relative;
    padding-left: 1.2rem;
}

.stack-function::before {
    content: '→';
    position: absolute;
    left: 0;
    color: var(--text-muted);
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--text-muted);
    font-style: italic;
    background: var(--surface-tertiary);
    border-radius: 16px;
    border: 1px dashed var(--border);
}

::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: var(--surface-tertiary);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 10px;
    transition: background 0.3s ease;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-light);
}

* {
    scrollbar-width: thin;
    scrollbar-color: var(--secondary) var(--surface-tertiary);
}

.stack-trace {
    max-height: none;
    box-shadow: var(--shadow-sm);
    margin-bottom: 2rem;
}

.code-context {
    max-width: 100%;
    overflow-x: auto;
}

.line-content {
    white-space: pre-wrap;
    word-break: break-all;
}

@media (max-width: 768px) {
    .code-context {
        padding: 1.5rem 1rem 1.5rem 0.5rem;
    }

    .line-number {
        width: 3ch;
        margin-right: 0.75rem;
    }

    .error-container {
        display: block;
    }

    .error-details,
    .stack-item,
    .code-context {
        border-radius: 8px;
    }
}

@media (max-width: 1200px) {

    .error-header-content,
    .error-content {
        max-width: 100%;
    }
}

@media (max-width: 992px) {
    .error-header {
        padding: 4rem 2rem;
    }

    .error-title {
        font-size: 2.5rem;
    }

    .error-icon {
        width: 80px;
        height: 80px;
        font-size: 36px;
    }

    .error-content {
        padding: 2.5rem;
    }
}

@media (max-width: 768px) {
    .error-header {
        padding: 3rem 1.5rem;
    }

    .error-header-content {
        padding: 0 1.5rem;
    }

    .error-title-container {
        max-width: 100%;
    }

    .error-title {
        font-size: 2rem;
        margin-bottom: 0.75rem;
    }

    .error-content {
        padding: 1.5rem;
    }

    .error-details,
    .code-context {
        padding: 1.5rem;
    }

    .section {
        margin-bottom: 2rem;
    }

    .stack-item {
        padding: 1.2rem 1.5rem;
    }
}

@media (max-width: 576px) {
    .error-header {
        padding: 2.5rem 1.25rem;
    }

    .error-icon {
        width: 70px;
        height: 70px;
        font-size: 30px;
    }

    .error-title {
        font-size: 1.75rem;
    }

    .error-content {
        padding: 1.25rem;
    }

    .error-details,
    .code-context {
        padding: 1.25rem;
    }

    .line-number {
        margin-right: 1rem;
    }

    .stack-item {
        padding: 1rem 1.25rem;
    }
}

@media (prefers-color-scheme: dark) {
    :root {
        --surface: #121212;
        --surface-secondary: #1e1e1e;
        --surface-tertiary: #2c2c2c;
        --text-primary: #e2e8f0;
        --text-secondary: #a0aec0;
        --text-muted: #718096;
        --border: #2d3748;
        --border-subtle: #2d3748;
    }

    .error-details {
        background: rgba(185, 28, 28, 0.1);
    }

    .code-line {
        color: #cbd5e0;
    }

    .stack-trace {
        background: var(--surface-secondary);
    }
}

.error-header-content {
    padding: 0 2rem;
    box-sizing: border-box;
    width: 100%;
}

.error-title-container {
    max-width: calc(100% - 120px);
    word-wrap: break-word;
    word-break: break-word;
}

.error-title {
    overflow-wrap: break-word;
    word-wrap: break-word;
    hyphens: auto;
}

@media (max-width: 768px) {
    .error-title-container {
        max-width: 100%;
    }
}