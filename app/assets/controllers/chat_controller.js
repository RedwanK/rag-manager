import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'conversationList',
        'conversationTitle',
        'conversationMeta',
        'thread',
        'threadEmpty',
        'promptInput',
        'sendButton',
        'streamStatus',
        'tokenCount',
        'cancelButton',
        'retryButton',
        'emptyConversations',
        'composerForm',
    ];

    static values = {
        conversationsUrl: String,
        messagesUrl: String,
        promptUrl: String,
        cancelUrl: String,
        streamUrl: String,
        createConversationUrl: String,
        initialConversations: Array,
        activeConversationId: Number,
    };

    connect() {
        this.conversations = this.initialConversationsValue || [];
        this.activeConversationId = this.hasActiveConversationIdValue ? this.activeConversationIdValue : null;
        this.lastPrompt = '';
        this.isSending = false;
        this.streamSource = null;
        this.streamingMessageId = null;

        this.renderConversations();
        if (this.activeConversationId) {
            this.selectConversation(this.activeConversationId);
        }
        this.refreshConversations();
    }

    disconnect() {
        this.closeStream();
    }

    async refreshConversations() {
        try {
            const response = await fetch(this.conversationsUrlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error('unable to load conversations');
            }

            const data = await response.json();
            this.conversations = data.map((conversation) => ({ ...conversation }));
            this.renderConversations();

            if (this.activeConversationId && this.conversations.some((c) => c.id === this.activeConversationId)) {
                this.selectConversation(this.activeConversationId);
            } else if (!this.activeConversationId && this.conversations.length > 0) {
                this.selectConversation(this.conversations[0].id);
            }

            this.toggleEmptyStates();
        } catch (error) {
            this.renderError('Impossible de charger les discussions.');
        }
    }

    toggleEmptyStates() {
        const hasConversations = this.conversations.length > 0;
        if (this.hasEmptyConversationsTarget) {
            this.emptyConversationsTarget.hidden = hasConversations;
        }

        if (!hasConversations) {
            this.showEmptyThread();
        }
    }

    renderConversations() {
        if (!this.hasConversationListTarget) {
            return;
        }

        if (this.conversations.length === 0) {
            this.conversationListTarget.innerHTML = '';
            this.toggleEmptyStates();
            return;
        }

        this.conversationListTarget.innerHTML = this.conversations
            .map((conversation) => this.conversationItemTemplate(conversation))
            .join('');

        this.conversationListTarget.querySelectorAll('[data-action="chat#selectConversation"]')
            .forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    const id = Number.parseInt(button.dataset.id, 10);
                    this.selectConversation(id);
                });
            });

        this.toggleEmptyStates();
    }

    conversationItemTemplate(conversation) {
        const active = conversation.id === this.activeConversationId;
        const statusBadge = this.buildConversationBadge(conversation);
        return `
            <button class="list-group-item list-group-item-action d-flex align-items-start gap-3 ${active ? 'active' : ''}" data-action="chat#selectConversation" data-id="${conversation.id}">
                <div class="avatar bg-label-primary text-primary">
                    <span>${(conversation.title || 'N').charAt(0).toUpperCase()}</span>
                </div>
                <div class="flex-grow-1 text-start">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="fw-semibold text-truncate">${this.escape(conversation.title || 'Nouvelle discussion')}</div>
                        ${statusBadge}
                    </div>
                    <div class="small text-muted">${this.formatTimestamp(conversation.lastActivityAt)}</div>
                </div>
            </button>
        `;
    }

    buildConversationBadge(conversation) {
        if (conversation.lastMessageStatus === 'error') {
            return '<span class="badge bg-label-danger">!</span>';
        }

        if (conversation.lastMessageId && conversation.lastSeenMessageId && conversation.lastSeenMessageId < conversation.lastMessageId) {
            return '<span class="badge bg-label-primary">●</span>';
        }

        return '<span class="badge bg-label-secondary"> </span>';
    }

    async selectConversation(conversationId) {
        if (this.streamSource && this.isSending) {
            return;
        }

        this.activeConversationId = conversationId;
        this.renderConversations();
        this.renderLoadingThread();
        await this.loadMessages(conversationId);
    }

    renderLoadingThread() {
        if (!this.hasThreadTarget) {
            return;
        }
        this.threadTarget.innerHTML = `<div class="text-center text-muted py-5">${this.spinnerTemplate('Chargement...')}</div>`;
        this.threadEmptyTarget.hidden = true;
    }

    showEmptyThread() {
        if (this.hasThreadTarget) {
            this.threadTarget.innerHTML = '';
        }
        if (this.hasThreadEmptyTarget) {
            this.threadEmptyTarget.hidden = false;
        }
        if (this.hasConversationTitleTarget) {
            this.conversationTitleTarget.textContent = this.conversations.length === 0
                ? this.translate('chat.thread.title')
                : this.translate('chat.thread.title');
        }
        if (this.hasConversationMetaTarget) {
            this.conversationMetaTarget.textContent = this.conversations.length === 0
                ? this.translate('chat.thread.placeholder')
                : this.translate('chat.thread.placeholder');
        }
    }

    async loadMessages(conversationId) {
        this.closeStream();
        this.isSending = false;
        this.updateComposerState(false);

        try {
            const url = this.messagesUrlValue.replace('/0/', `/${conversationId}/`);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error('messages');
            }

            const messages = await response.json();
            this.renderThread(messages);
            this.markConversationSeen(conversationId, messages);
            this.updateConversationHeader(conversationId);
            this.updateLastPrompt(messages);
        } catch (error) {
            this.renderError('Impossible de charger les messages.');
        }
    }

    updateLastPrompt(messages) {
        const lastUserMessage = [...messages].reverse().find((message) => message.role === 'user');
        if (lastUserMessage && lastUserMessage.content) {
            this.lastPrompt = lastUserMessage.content;
        }
    }

    markConversationSeen(conversationId, messages) {
        const lastMessage = messages.at(-1);
        this.conversations = this.conversations.map((conversation) => {
            if (conversation.id !== conversationId) {
                return conversation;
            }

            return {
                ...conversation,
                lastMessageId: lastMessage?.id ?? null,
                lastMessageStatus: lastMessage?.status ?? null,
                lastMessageRole: lastMessage?.role ?? null,
                lastSeenMessageId: lastMessage?.id ?? conversation.lastSeenMessageId,
            };
        });
        this.renderConversations();
    }

    updateConversationHeader(conversationId) {
        const conversation = this.conversations.find((item) => item.id === conversationId);
        if (!conversation) {
            return;
        }

        if (this.hasConversationTitleTarget) {
            this.conversationTitleTarget.textContent = conversation.title || 'Nouvelle discussion';
        }

        if (this.hasConversationMetaTarget) {
            this.conversationMetaTarget.textContent = `${this.translate('chat.thread.last_activity')} ${this.formatTimestamp(conversation.lastActivityAt)}`;
        }
    }

    renderThread(messages) {
        if (messages.length === 0) {
            this.threadTarget.innerHTML = '';
            if (this.hasThreadEmptyTarget) {
                this.threadEmptyTarget.hidden = false;
            }
            if (this.hasRetryButtonTarget) {
                this.retryButtonTarget.hidden = true;
            }
            return;
        }

        if (this.hasThreadEmptyTarget) {
            this.threadEmptyTarget.hidden = true;
        }

        const threadHtml = messages.map((message) => this.messageTemplate(message)).join('');
        this.threadTarget.innerHTML = threadHtml;
        this.threadTarget.scrollTop = this.threadTarget.scrollHeight;
        if (this.hasRetryButtonTarget) {
            this.retryButtonTarget.hidden = !messages.some((message) => message.status === 'error');
        }
    }

    messageTemplate(message) {
        const isUser = message.role === 'user';
        const bubbleClass = isUser ? 'chat-bubble-user' : 'chat-bubble-assistant';
        const alignment = isUser ? 'flex-row-reverse text-end' : '';
        const icon = isUser ? 'bx:user' : 'bx:bot';
        const timestamp = message.finishedAt || message.streamedAt || message.createdAt;
        const statusBadge = this.messageStatusBadge(message);
        const sources = this.renderSources(message.sourceDocuments);
        const format = message.format || 'markdown';

        return `
            <div class="d-flex gap-3 mb-4 ${alignment}" data-format="${format}">
                <div class="avatar ${isUser ? 'bg-primary text-white' : 'bg-label-secondary text-muted'}">
                    <span>${this.icon(icon)}</span>
                </div>
                <div class="flex-grow-1">
                    <div class="chat-bubble ${bubbleClass} ${message.status === 'error' ? 'border border-danger' : ''}" data-message-id="${message.id}" data-format="${format}">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <span class="fw-semibold">${isUser ? this.translate('chat.roles.user') : this.translate('chat.roles.assistant')}</span>
                            <small class="text-muted">${this.formatTimestamp(timestamp)}</small>
                        </div>
                        <div class="chat-message-content" data-raw="${this.escapeAttribute(message.content || '')}">${this.renderMessageContent(message.content || (message.status === 'streaming' ? this.translate('chat.streaming.waiting') : ''), format)}</div>
                        ${sources}
                        ${statusBadge}
                    </div>
                </div>
            </div>
        `;
    }

    messageStatusBadge(message) {
        if (message.status === 'error') {
            return `
                <div class="alert alert-danger d-flex align-items-start gap-2 mt-3 mb-0">
                    ${this.icon('bx:error-circle')}
                    <div>
                        <div class="fw-semibold mb-1">${this.translate('chat.errors.title')}</div>
                        <div class="small">${this.escape(message.error || this.translate('chat.errors.generic'))}</div>
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-outline-primary" data-action="click->chat#retryLastPrompt">${this.icon('bx:refresh')} ${this.translate('chat.actions.retry')}</button>
                        </div>
                    </div>
                </div>
            `;
        }

        if (message.status === 'streaming') {
            return `<div class="d-flex align-items-center gap-2 text-muted small mt-3 chat-streaming-indicator">${this.spinnerTemplate(this.translate('chat.streaming.live'))}</div>`;
        }

        return '';
    }

    renderSources(sources) {
        if (!sources || (Array.isArray(sources) && sources.length === 0)) {
            return '';
        }

        const items = Array.isArray(sources) ? sources : [];
        const rendered = items.map((source, index) => {
            if (typeof source === 'string') {
                return `<span class="badge bg-label-secondary me-1">${this.escape(source)}</span>`;
            }

            const label = this.escape(source.title || source.path || source.url || `${this.translate('chat.sources.item')} ${index + 1}`);
            if (source.url) {
                return `<a class="badge bg-label-secondary text-decoration-none me-1" target="_blank" rel="noreferrer" href="${source.url}">${label}</a>`;
            }

            return `<span class="badge bg-label-secondary me-1">${label}</span>`;
        }).join('');

        if (rendered === '') {
            return '';
        }

        return `<div class="mt-3"><div class="small text-muted mb-1">${this.translate('chat.sources.title')}</div>${rendered}</div>`;
    }

    async submitPrompt(event) {
        event.preventDefault();
        if (this.isSending || !this.activeConversationId) {
            return;
        }

        const prompt = this.promptInputTarget.value.trim();
        if (prompt === '') {
            return;
        }

        this.lastPrompt = prompt;
        this.isSending = true;
        this.updateComposerState(true);
        this.retryButtonTarget.hidden = true;

        try {
            const url = this.promptUrlValue.replace('/0/prompt', `/${this.activeConversationId}/prompt`);
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ prompt }),
            });

            console.log(response);

            if (!response.ok) {
                const errorPayload = await response.json().catch(() => ({}));
                const message = errorPayload.message || this.translate('chat.errors.generic');
                throw new Error(message);
            }

            const payload = await response.json();
            this.appendUserMessage(prompt);
            this.appendAssistantPlaceholder(payload.assistantMessageId);
            this.streamAssistant(payload.assistantMessageId);
            this.promptInputTarget.value = '';
        } catch (error) {
            this.isSending = false;
            this.updateComposerState(false);
            this.retryButtonTarget.hidden = false;
            this.renderError(error.message || this.translate('chat.errors.generic'));
        }
    }

    updateComposerState(disabled) {
        if (this.hasSendButtonTarget) {
            this.sendButtonTarget.disabled = disabled;
        }
        if (this.hasPromptInputTarget) {
            this.promptInputTarget.readOnly = disabled;
        }
        if (this.hasStreamStatusTarget) {
            this.streamStatusTarget.hidden = !disabled;
        }
        if (this.hasCancelButtonTarget) {
            this.cancelButtonTarget.classList.toggle('d-none', !disabled);
        }
        if (!disabled && this.hasTokenCountTarget) {
            this.tokenCountTarget.textContent = '0';
        }
    }

    appendUserMessage(content) {
        const message = {
            id: Date.now(),
            role: 'user',
            content,
            format: 'markdown',
            status: 'completed',
            createdAt: new Date().toISOString(),
            finishedAt: new Date().toISOString(),
        };

        this.threadTarget.insertAdjacentHTML('beforeend', this.messageTemplate(message));
        this.threadTarget.scrollTop = this.threadTarget.scrollHeight;
        this.threadEmptyTarget.hidden = true;
    }

    appendAssistantPlaceholder(messageId) {
        this.streamingMessageId = messageId;
        const message = {
            id: messageId,
            role: 'assistant',
            content: '',
            format: 'markdown',
            status: 'streaming',
            createdAt: new Date().toISOString(),
        };
        this.threadTarget.insertAdjacentHTML('beforeend', this.messageTemplate(message));
        this.threadTarget.scrollTop = this.threadTarget.scrollHeight;
    }

    streamAssistant(messageId) {
        const streamUrl = this.streamUrlValue.replace('/0/0', `/${this.activeConversationId}/${messageId}`);
        this.tokenCountTarget.textContent = '0';
        let tokens = 0;
        let streamCompleted = false;

        this.streamSource = new EventSource(streamUrl);

        this.streamSource.addEventListener('token', (event) => {
            const payload = JSON.parse(event.data || '{}');
            tokens += 1;
            this.tokenCountTarget.textContent = String(tokens);
            this.updateStreamingContent(messageId, payload.text || '');
        });

        this.streamSource.addEventListener('sources', (event) => {
            const payload = JSON.parse(event.data || '[]');
            this.attachSources(messageId, payload);
        });

        this.streamSource.addEventListener('error', (event) => {
            if (streamCompleted) {
                return;
            }
            const data = this.safeParse(event.data);
            const message = data?.message || this.translate('chat.errors.generic');
            this.handleStreamError(messageId, message);
        });

        this.streamSource.addEventListener('done', () => {
            streamCompleted = true;
            this.handleStreamCompletion(messageId);
        });

        this.streamSource.onerror = () => {
            if (streamCompleted || (this.streamSource && this.streamSource.readyState === EventSource.CLOSED)) {
                return;
            }
            this.handleStreamError(messageId, this.translate('chat.errors.generic'));
        };
    }

    updateStreamingContent(messageId, text) {
        const bubble = this.findMessageBubble(messageId);
        if (!bubble) {
            return;
        }
        const content = bubble.querySelector('.chat-message-content');
        if (!content) {
            return;
        }
        const current = content.getAttribute('data-raw') || '';
        const next = `${current}${text}`;
        content.setAttribute('data-raw', next);
        const format = bubble.dataset.format || 'markdown';
        content.innerHTML = this.renderMessageContent(next, format);
        bubble.classList.add('chat-bubble-assistant-active');
        this.threadTarget.scrollTop = this.threadTarget.scrollHeight;
    }

    attachSources(messageId, sources) {
        const bubble = this.findMessageBubble(messageId);
        if (!bubble) {
            return;
        }
        const existing = bubble.querySelector('[data-chat-sources]');
        if (existing) {
            existing.remove();
        }
        const wrapper = document.createElement('div');
        wrapper.setAttribute('data-chat-sources', '');
        wrapper.innerHTML = this.renderSources(sources);
        bubble.appendChild(wrapper);
    }

    handleStreamCompletion(messageId) {
        this.isSending = false;
        this.updateComposerState(false);
        this.closeStream();
        this.refreshConversations();
        this.streamingMessageId = null;
        const bubble = this.findMessageBubble(messageId);
        if (!bubble) {
            return;
        }
        bubble.querySelectorAll('.alert, .chat-streaming-indicator').forEach((element) => element.remove());
        bubble.classList.remove('chat-bubble-assistant-active');
    }

    handleStreamError(messageId, message) {
        this.isSending = false;
        this.updateComposerState(false);
        this.closeStream();
        this.retryButtonTarget.hidden = false;

        const bubble = this.findMessageBubble(messageId);
        if (!bubble) {
            this.renderError(message);
            return;
        }

        const content = bubble.querySelector('.chat-message-content');
        if (content && (content.getAttribute('data-raw') || '').trim() !== '') {
            // If we already received content, avoid overriding with empty error; just show banner.
        }

        bubble.classList.add('border', 'border-danger');
        const existingAlert = bubble.querySelector('.alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        bubble.insertAdjacentHTML('beforeend', `
            <div class="alert alert-danger d-flex align-items-start gap-2 mt-3 mb-0">
                ${this.icon('bx:error-circle')}
                <div>
                    <div class="fw-semibold mb-1">${this.translate('chat.errors.title')}</div>
                    <div class="small">${this.escape(message)}</div>
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        <button class="btn btn-sm btn-outline-primary" data-action="click->chat#retryLastPrompt">${this.icon('bx:refresh')} ${this.translate('chat.actions.retry')}</button>
                    </div>
                </div>
            </div>
        `);
    }

    async newConversation() {
        if (this.isSending) {
            return;
        }
        try {
            const response = await fetch(this.createConversationUrlValue, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: JSON.stringify({}),
            });
            if (!response.ok) {
                throw new Error('create');
            }
            const conversation = await response.json();
            this.conversations.unshift({ ...conversation });
            this.renderConversations();
            this.toggleEmptyStates();
            this.selectConversation(conversation.id);
            this.promptInputTarget.focus();
        } catch (error) {
            this.renderError('Impossible de créer une discussion.');
        }
    }

    async retryLastPrompt(event) {
        event?.preventDefault();
        if (this.isSending) {
            return;
        }
        if (!this.lastPrompt) {
            return;
        }
        this.promptInputTarget.value = this.lastPrompt;
        this.submitPrompt({ preventDefault() {} , target: this.composerFormTarget });
    }

    async cancelStream() {
        if (!this.streamingMessageId || !this.activeConversationId) {
            return;
        }

        try {
            const url = this.cancelUrlValue.replace('/0', `/${this.streamingMessageId}`);
            await fetch(url, { method: 'POST', headers: { Accept: 'application/json' } });
        } catch (error) {
            // Ignored
        } finally {
            this.handleStreamError(this.streamingMessageId, this.translate('chat.errors.cancelled'));
        }
    }

    closeStream() {
        if (this.streamSource) {
            this.streamSource.close();
            this.streamSource = null;
        }
    }

    renderError(message) {
        if (!this.hasThreadTarget) {
            return;
        }
        this.threadTarget.insertAdjacentHTML('afterbegin', `
            <div class="alert alert-warning d-flex align-items-start gap-2">${this.icon('bx:info-circle')}<div>${this.escape(message)}</div></div>
        `);
    }

    findMessageBubble(messageId) {
        return this.threadTarget.querySelector(`.chat-bubble[data-message-id="${messageId}"]`) || this.threadTarget.querySelector('.chat-bubble:last-child');
    }

    spinnerTemplate(label) {
        return `<span class="spinner-border spinner-border-sm"></span><span>${label}</span>`;
    }

    icon(name) {
        const iconName = name.includes(':') ? name.split(':')[1] : name;
        const normalized = iconName.startsWith('bx-') ? iconName : `bx-${iconName}`;
        return `<i class="bx ${normalized}"></i>`;
    }

    escape(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    escapeAttribute(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML.replace(/"/g, '&quot;');
    }

    renderMessageContent(value, format = 'markdown') {
        const text = value || '';
        if (format !== 'markdown') {
            return this.escape(text);
        }

        // Minimal Markdown rendering: headings, bold/italic, code, lists, line breaks.
        let html = this.escape(text);
        html = html.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');

        // Lists
        html = html.replace(/(?:^|\n)[-•]\s+([^\n]+)/g, (match, item) => `<li>${item.trim()}</li>`);
        if (html.includes('<li>')) {
            html = html.replace(/(<li>[\s\S]*<\/li>)/g, '<ul class="mb-2">$1</ul>');
        }

        // Headings
        html = html.replace(/^###\s+(.*)$/gm, '<h6 class="mt-2 mb-1">$1</h6>');
        html = html.replace(/^##\s+(.*)$/gm, '<h5 class="mt-2 mb-1">$1</h5>');
        html = html.replace(/^#\s+(.*)$/gm, '<h4 class="mt-2 mb-1">$1</h4>');

        // Line breaks to <br> and paragraphs
        html = html.replace(/\n{2,}/g, '</p><p>');
        html = `<p>${html.replace(/\n/g, '<br>')}</p>`;

        return html;
    }

    translate(key) {
        const translations = {
            'chat.thread.title': 'Assistant',
            'chat.thread.placeholder': 'Sélectionnez une conversation pour commencer.',
            'chat.thread.last_activity': 'Dernière activité :',
            'chat.streaming.waiting': 'Réponse en cours...',
            'chat.streaming.live': 'Génération en cours',
            'chat.streaming.tokens': 'tokens',
            'chat.roles.user': 'Vous',
            'chat.roles.assistant': 'Assistant',
            'chat.errors.title': 'Une erreur est survenue',
            'chat.errors.generic': 'Une erreur inconnue est survenue.',
            'chat.errors.cancelled': 'Génération annulée.',
            'chat.sources.title': 'Sources',
            'chat.sources.item': 'Source',
            'chat.actions.retry': 'Réessayer',
        };

        return translations[key] || key;
    }

    formatTimestamp(value) {
        if (!value) {
            return '';
        }
        try {
            const date = new Date(value);
            return new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short' }).format(date);
        } catch (error) {
            return value;
        }
    }

    safeParse(value) {
        try {
            return JSON.parse(value);
        } catch (error) {
            return null;
        }
    }
}
