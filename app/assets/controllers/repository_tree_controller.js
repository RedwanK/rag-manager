import { Controller } from '@hotwired/stimulus';
import * as d3 from 'd3';

export default class extends Controller {
    static targets = ['canvas', 'details', 'filterButton'];
    static values = {
        tree: Object,
        enqueueUrlTemplate: String,
        translations: Object,
        filter: { type: String, default: 'all' },
        filterStorageKey: { type: String, default: 'repository-tree-filter' }
    };

    connect() {
        this.selectedNode = null;
        this.filterValue = this.loadFilter();
        this.handleResize = this.refresh.bind(this);
        this.build();
        window.addEventListener('resize', this.handleResize);
    }

    disconnect() {
        window.removeEventListener('resize', this.handleResize);
    }

    build() {
        if (!this.treeValue || !this.hasCanvasTarget) {
            return;
        }

        this.canvasTarget.innerHTML = '';

        this.root = d3.hierarchy(this.getFilteredTree());
        this.root.x0 = 0;
        this.root.y0 = 0;
        this.root.descendants().forEach(node => {
            node.id = node.data.path || node.data.name;
            node._children = node.children;
            if (node.depth > 1) {
                node.children = null;
            }
        });

        this.svg = d3.select(this.canvasTarget).append('svg');
        this.g = this.svg.append('g').attr('transform', 'translate(60,20)');

        this.update(this.root);
        this.showDetails(this.root);
        this.updateFilterButtons();
    }

    refresh() {
        if (this.root) {
            this.update(this.root);
        }
    }

    update(source) {
        const width = this.canvasTarget.clientWidth || 720;
        const nodes = this.root.descendants();
        const links = this.root.links();
        const height = Math.max(360, nodes.length * 26);

        const tree = d3.tree().size([height, width - 200]);
        tree(this.root);

        const maxNodeDepth = d3.max(nodes, d => d.y) || width;
        const estimatedLabelWidth = d3.max(nodes, d => this.estimateLabelWidth(d.data.name)) || 0;
        const canvasWidth = Math.max(width, maxNodeDepth + estimatedLabelWidth + 120);

        this.svg.attr('width', canvasWidth).attr('height', height + 40);

        const node = this.g.selectAll('g.node').data(nodes, d => d.id);

        const nodeEnter = node
            .enter()
            .append('g')
            .attr('class', 'node')
            .attr('transform', () => `translate(${source.y0},${source.x0})`)
            .on('click', (_, d) => this.onNodeClick(d));

        nodeEnter
            .append('circle')
            .attr('class', 'repo-tree-node')
            .attr('r', 1e-6)
            .style('fill', d => this.nodeColor(d));

        nodeEnter
            .append('title')
            .text(d => d.data.path || d.data.name || '');

        nodeEnter
            .append('text')
            .attr('dy', '0.32em')
            .attr('x', d => (d.children || d._children ? -14 : 14))
            .attr('text-anchor', d => (d.children || d._children ? 'end' : 'start'))
            .text(d => this.truncateLabel(d.data.name));

        const nodeUpdate = nodeEnter.merge(node);

        nodeUpdate
            .transition()
            .duration(250)
            .attr('transform', d => `translate(${d.y},${d.x})`);

        nodeUpdate
            .select('circle.repo-tree-node')
            .attr('r', 7)
            .classed('is-leaf', d => !d.children && !d._children)
            .style('fill', d => this.nodeColor(d))
            .style('stroke', d => (this.selectedNode && this.selectedNode.id === d.id ? '#696cff' : '#e9ecef'))
            .style('stroke-width', this.selectedNode ? 2 : 1);

        nodeUpdate
            .select('text')
            .attr('x', d => (d.children || d._children ? -14 : 14))
            .attr('text-anchor', d => (d.children || d._children ? 'end' : 'start'))
            .text(d => this.truncateLabel(d.data.name));

        const nodeExit = node
            .exit()
            .transition()
            .duration(200)
            .attr('transform', () => `translate(${source.y},${source.x})`)
            .remove();

        nodeExit.select('circle').attr('r', 1e-6);
        nodeExit.select('text').style('fill-opacity', 1e-6);

        const link = this.g.selectAll('path.link').data(links, d => d.target.id);

        const linkEnter = link
            .enter()
            .insert('path', 'g')
            .attr('class', 'link')
            .attr('d', () => this.curve({ source: { x: source.x0, y: source.y0 }, target: { x: source.x0, y: source.y0 } }));

        linkEnter.merge(link)
            .transition()
            .duration(250)
            .attr('d', d => this.curve(d));

        link
            .exit()
            .transition()
            .duration(200)
            .attr('d', () => this.curve({ source: { x: source.x, y: source.y }, target: { x: source.x, y: source.y } }))
            .remove();

        nodes.forEach(d => {
            d.x0 = d.x;
            d.y0 = d.y;
        });
    }

    onNodeClick(node) {
        if (node.children) {
            node._children = node.children;
            node.children = null;
        } else if (node._children) {
            node.children = node._children;
            node._children = null;
        }

        this.selectedNode = node;
        this.showDetails(node);
        this.update(node);
    }

    expandAll() {
        if (!this.root) return;
        this.root.descendants().forEach(node => {
            if (node._children) {
                node.children = node._children;
                node._children = null;
            }
        });
        this.update(this.root);
    }

    collapseAll() {
        if (!this.root) return;
        this.root.descendants().forEach(node => {
            if (node.depth > 0 && node.children) {
                node._children = node.children;
                node.children = null;
            }
        });
        this.update(this.root);
    }

    resetView() {
        if (!this.root) return;
        this.root.descendants().forEach(node => {
            if (node.depth <= 1) {
                if (node._children) {
                    node.children = node._children;
                    node._children = null;
                }
            } else {
                if (node.children) {
                    node._children = node.children;
                }
                node.children = null;
            }
        });
        this.selectedNode = this.root;
        this.showDetails(this.root);
        this.update(this.root);
    }

    showDetails(node) {
        if (!this.hasDetailsTarget) return;

        const data = node?.data || {};
        const lastSync = data.lastSyncedAt ? new Date(data.lastSyncedAt) : null;
        const status = this.nodeStatus(data);
        const ingestion = this.ingestionStatus(data);
        const enqueueAction = this.renderEnqueueAction(data);
        const tDetails = (this.translationsValue && this.translationsValue.details) || {};

        this.detailsTarget.innerHTML = `
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div>
                    <div class="small text-muted">${this.escape(tDetails.path || 'Chemin')}</div>
                    <div class="fw-semibold">${this.escape(data.path || data.name || '')}</div>
                </div>
                <span class="badge bg-label-${status.color} text-uppercase">${status.label}</span>
            </div>
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted">${this.escape(tDetails.type || 'Type')}</dt>
                <dd class="col-7 text-capitalize mb-2">${this.escape(data.type || 'n/a')}</dd>
                <dt class="col-5 text-muted">${this.escape(tDetails.size || 'Taille')}</dt>
                <dd class="col-7 mb-2">${this.formatSize(data.size)}</dd>
                <dt class="col-5 text-muted">${this.escape(tDetails.lastSync || 'Dernière synchro')}</dt>
                <dd class="col-7 mb-2">${lastSync ? lastSync.toLocaleString() : this.escape(tDetails.never || 'Jamais')}</dd>
                <dt class="col-5 text-muted">${this.escape(tDetails.ingestion || 'Ingestion')}</dt>
                <dd class="col-7 mb-2"><span class="badge bg-label-${ingestion.color} text-uppercase">${ingestion.label}</span></dd>
            </dl>
            ${enqueueAction}
        `;
    }

    nodeStatus(data) {
        const status = (data.lastSyncStatus || 'n/a').toLowerCase();
        if (status === 'failed') {
            return { label: status, color: 'danger' };
        }
        if (status === 'synced' || status === 'success') {
            return { label: status, color: 'success' };
        }
        return { label: status, color: 'secondary' };
    }

    nodeColor(node) {
        const status = this.ingestionStatus(node.data);
        
        if (node.data.type === 'tree') return '#696cff';

        switch(status.color) {
            case 'danger': 
                return '#ff3e1d';
            case 'warning': 
                return '#ffab00';
            case 'info':
                return '#03c3ec';
            case 'success':
                return '#71dd37';
            case 'secondary':
                return '#8592a3';
        }
        
    }

    curve(d) {
        return `M ${d.source.y},${d.source.x}
            C ${(d.source.y + d.target.y) / 2},${d.source.x}
              ${(d.source.y + d.target.y) / 2},${d.target.x}
              ${d.target.y},${d.target.x}`;
    }

    formatSize(value) {
        if (!value) {
            return '—';
        }
        const units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        let size = value;
        let unit = 0;
        while (size >= 1024 && unit < units.length - 1) {
            size /= 1024;
            unit += 1;
        }
        return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
    }

    escape(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    parseTree(tree) {
        return JSON.parse(JSON.stringify(tree));
    }

    ingestionStatus(data) {
        const status = (data.ingestionStatus || 'unindexed').toLowerCase();
        const labels = (this.translationsValue && this.translationsValue.ingestion) || {};
        
        if(status === "unindexed" && !data.canEnqueue) return { label: labels.cant_enqueue || 'cant_enqueue', color: 'secondary' };

        switch (status) {
            case 'processing':
            case 'queued':
                return { label: labels.queued || 'queued', color: 'warning' };
            case 'indexed':
                return { label: labels.indexed || 'indexed', color: 'success' };
            case 'failed':
            case 'download_failed':
                return { label: labels[status] || status, color: 'danger' };
            default:
                return { label: labels.unindexed || 'unindexed', color: 'info' };
        }
    }

    renderEnqueueAction(data) {
        const t = this.translationsValue || {};
        if (data.type !== 'blob') {
            return `<div class="text-muted small mt-2">${this.escape(t.folderHint || '')}</div>`;
        }

        const canEnqueue = Boolean(data.canEnqueue);
        const urlTemplate = this.enqueueUrlTemplateValue || '';
        const actionUrl = data.id ? urlTemplate.replace('NODE_ID', data.id) : '';
        const disabledReason = t.disabledReason || '';
        const enqueueLabel = t.enqueueLabel || '';
        const retryLabel = t.retryLabel || enqueueLabel;
        const status = (data.ingestionStatus || 'unindexed').toLowerCase();
        const statusLabels = (t.ingestion) || {};

        const inactiveStatuses = ['queued', 'processing', 'indexed'];
        if (inactiveStatuses.includes(status)) {
            const tooltip = status === 'indexed' ? (t.indexedReason || disabledReason) : (t.inProgressReason || disabledReason);
            return `
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="${this.escape(tooltip)}">
                        ${this.escape(statusLabels[status] || status)}
                    </button>
                </div>
            `;
        }

        const isIndexableStatus = ['unindexed', 'failed', 'download_failed'].includes(status);
        if (!isIndexableStatus) {
            return '';
        }

        const label = status === 'unindexed' ? enqueueLabel : retryLabel;
        const tooltip = canEnqueue ? '' : disabledReason;

        return `
            <form method="post" action="${actionUrl}" data-turbo="false" class="mt-2">
                <input type="hidden" name="_token" value="${this.escape(data.enqueueToken || '')}">
                <button type="submit" class="btn btn-sm btn-primary" ${canEnqueue ? '' : 'disabled'} title="${this.escape(tooltip)}">
                    ${this.escape(label)}
                </button>
            </form>
        `;
    }

    getFilteredTree() {
        const tree = this.parseTree(this.treeValue);
        if (this.filterValue === 'all') {
            return tree;
        }

        const predicate = (node) => {
            const status = (node.ingestionStatus || 'unindexed').toLowerCase();
            switch (this.filterValue) {
                case 'indexed':
                    return status === 'indexed';
                case 'failed':
                    return ['failed', 'download_failed'].includes(status);
                case 'indexable':
                    return node.type === 'blob' && node.canEnqueue && ['unindexed', 'failed', 'download_failed'].includes(status);
                default:
                    return true;
            }
        };

        return this.filterTree(tree, predicate, true);
    }

    filterTree(node, predicate, isRoot = false) {
        const children = (node.children || []).map(child => this.filterTree(child, predicate, false)).filter(Boolean);
        const matches = predicate(node);

        if (!matches && children.length === 0 && !isRoot) {
            return null;
        }

        return { ...node, children };
    }

    setFilter(event) {
        const filter = event.params.filter;
        this.filterValue = filter;
        this.saveFilter(filter);
        this.selectedNode = null;
        this.build();
    }

    loadFilter() {
        const stored = window.localStorage.getItem(this.filterStorageKeyValue);
        return stored || this.filterValue;
    }

    saveFilter(value) {
        window.localStorage.setItem(this.filterStorageKeyValue, value);
    }

    updateFilterButtons() {
        if (!this.hasFilterButtonTargets) return;
        this.filterButtonTargets.forEach(button => {
            const current = button.dataset.repositoryTreeFilterParam;
            if (current === this.filterValue) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
        });
    }

    truncateLabel(label) {
        if (!label) return '';
        const maxLength = Math.max(24, Math.min(48, Math.floor((this.canvasTarget.clientWidth || 240) / 10)));
        if (label.length <= maxLength) return label;
        return `${label.slice(0, maxLength - 1)}…`;
    }

    estimateLabelWidth(label) {
        if (!label) return 0;
        return Math.min(label.length, 48) * 7 + 24;
    }
}
