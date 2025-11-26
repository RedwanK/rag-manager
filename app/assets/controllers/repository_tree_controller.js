import { Controller } from '@hotwired/stimulus';
import * as d3 from 'd3';

export default class extends Controller {
    static targets = ['canvas', 'details'];
    static values = { tree: Object };

    connect() {
        this.selectedNode = null;
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

        this.root = d3.hierarchy(this.parseTree(this.treeValue));
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

        this.svg.attr('width', width).attr('height', height + 40);

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
            .append('text')
            .attr('dy', '0.32em')
            .attr('x', d => (d.children || d._children ? -14 : 14))
            .attr('text-anchor', d => (d.children || d._children ? 'end' : 'start'))
            .text(d => d.data.name);

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
            .attr('text-anchor', d => (d.children || d._children ? 'end' : 'start'));

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

        this.detailsTarget.innerHTML = `
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div>
                    <div class="small text-muted">Chemin</div>
                    <div class="fw-semibold">${this.escape(data.path || data.name || '')}</div>
                </div>
                <span class="badge bg-label-${status.color} text-uppercase">${status.label}</span>
            </div>
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted">Type</dt>
                <dd class="col-7 text-capitalize mb-2">${this.escape(data.type || 'n/a')}</dd>
                <dt class="col-5 text-muted">Taille</dt>
                <dd class="col-7 mb-2">${this.formatSize(data.size)}</dd>
                <dt class="col-5 text-muted">Dernière synchro</dt>
                <dd class="col-7 mb-2">${lastSync ? lastSync.toLocaleString() : 'Jamais'}</dd>
            </dl>
            <p class="text-muted mb-0">Sélectionnez un noeud pour préparer les actions futures (ouvrir, ignorer, supprimer...).</p>
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
        const status = this.nodeStatus(node.data);
        if (status.color === 'danger') {
            return '#ff3e1d';
        }
        return node.data.type === 'tree' ? '#696cff' : '#7987a1';
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
}
