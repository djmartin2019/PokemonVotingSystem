(() => {
    const SVG_NS = 'http://www.w3.org/2000/svg';

    // Validated categorical palette (dark steps), checked against the card surface #0c0e1a
    const PALETTE = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'];
    const ACCENT = '#5ef0ff';
    const SURFACE = '#0c0e1a';

    const fmt = new Intl.NumberFormat('en-US');
    const compact = (n) => (Math.abs(n) >= 1000 ? `${+(n / 1000).toFixed(n % 1000 === 0 ? 0 : 1)}K` : String(n));
    const titleCase = (s) => s.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    const formatDate = (s) => {
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        return Number.isNaN(d.getTime()) ? s : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };

    const readJson = (id) => {
        const node = document.getElementById(id);
        if (!node) return null;
        try {
            return JSON.parse(node.textContent);
        } catch {
            return null;
        }
    };

    const svgEl = (tag, attrs = {}, parent) => {
        const node = document.createElementNS(SVG_NS, tag);
        for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, v);
        if (parent) parent.appendChild(node);
        return node;
    };

    const htmlEl = (tag, className, text, parent) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        if (parent) parent.appendChild(node);
        return node;
    };

    const scale = (d0, d1, r0, r1) => (v) => (d1 === d0 ? (r0 + r1) / 2 : r0 + ((v - d0) / (d1 - d0)) * (r1 - r0));

    const niceTicks = (min, max, count = 5) => {
        const span = max - min || 1;
        const raw = span / count;
        const mag = 10 ** Math.floor(Math.log10(raw));
        const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw) || raw;
        const ticks = [];
        for (let v = Math.ceil(min / step) * step; v <= max + 1e-9; v += step) ticks.push(Math.round(v));
        return { ticks, step };
    };

    // Re-render a chart whenever its container changes width
    const mount = (container, render) => {
        let lastWidth = 0;
        const draw = () => {
            const width = Math.round(container.clientWidth);
            if (!width || width === lastWidth) return;
            lastWidth = width;
            container.replaceChildren();
            render(width);
        };
        new ResizeObserver(draw).observe(container);
        draw();
    };

    /* ---------------------------------------------------------------------
       Shared tooltip
       --------------------------------------------------------------------- */

    const tip = htmlEl('div', 'viz-tip', null, document.body);
    tip.setAttribute('role', 'status');
    tip.hidden = true;

    const showTip = (event, build) => {
        tip.replaceChildren();
        build(tip);
        tip.hidden = false;
        const pad = 14;
        const { innerWidth: vw, innerHeight: vh } = window;
        const rect = tip.getBoundingClientRect();
        let x = event.clientX + pad;
        let y = event.clientY + pad;
        if (x + rect.width > vw - 8) x = event.clientX - rect.width - pad;
        if (y + rect.height > vh - 8) y = event.clientY - rect.height - pad;
        tip.style.transform = `translate(${Math.max(8, x)}px, ${Math.max(8, y)}px)`;
    };

    const hideTip = () => {
        tip.hidden = true;
    };

    const tipHeader = (parent, text) => htmlEl('div', 'viz-tip__head', text, parent);

    const tipRow = (parent, { color, value, label, sub }) => {
        const row = htmlEl('div', 'viz-tip__row', null, parent);
        if (color) {
            const key = htmlEl('span', 'viz-tip__key', null, row);
            key.style.background = color;
        }
        htmlEl('span', 'viz-tip__value', value, row);
        htmlEl('span', 'viz-tip__label', label, row);
        if (sub) htmlEl('span', 'viz-tip__sub', sub, row);
        return row;
    };

    const isNarrow = (width) => width < 560;

    /* ---------------------------------------------------------------------
       Rankings race (bump chart)
       --------------------------------------------------------------------- */

    const renderRace = () => {
        const container = document.getElementById('race-chart');
        const data = readJson('race-data');
        if (!container || !data || data.points.length < 2) return;

        const MAX_RANK = 10;
        const OVERFLOW = MAX_RANK + 0.9;
        const series = data.series.map((s, i) => ({ ...s, color: PALETTE[i % PALETTE.length], label: titleCase(s.name) }));
        let focused = null;

        // Legend doubles as a highlighter
        const legend = document.getElementById('race-legend');
        if (legend) {
            legend.replaceChildren();
            series.forEach((s) => {
                const item = htmlEl('button', 'legend__item', null, legend);
                item.type = 'button';
                item.setAttribute('role', 'listitem');
                item.setAttribute('aria-pressed', 'false');
                const key = htmlEl('span', 'legend__key', null, item);
                key.style.background = s.color;
                htmlEl('span', null, s.label, item);
                item.addEventListener('mouseenter', () => setFocus(s.id));
                item.addEventListener('mouseleave', () => setFocus(null));
                item.addEventListener('focus', () => setFocus(s.id));
                item.addEventListener('blur', () => setFocus(null));
            });
        }

        let setFocus = () => {};

        mount(container, (width) => {
            const narrow = isNarrow(width);
            const height = narrow ? 360 : 400;
            const m = { top: 14, right: narrow ? 36 : 170, bottom: 34, left: 40 };
            // On small screens draw every other sample (always keeping the last) so the lines stay readable
            const keep = data.points.map((_, i) => !narrow || i % 2 === (data.points.length - 1) % 2);
            const innerW = width - m.left - m.right;
            const innerH = height - m.top - m.bottom;

            const battles = data.points.map((p) => p.battle);
            const x = scale(battles[0], battles[battles.length - 1], m.left, m.left + innerW);
            const y = scale(1, OVERFLOW, m.top, m.top + innerH);
            const yOf = (rank) => y(Math.min(rank, OVERFLOW));

            const svg = svgEl('svg', { width, height, viewBox: `0 0 ${width} ${height}`, class: 'viz' }, container);

            // Overflow band below the visible ranks
            svgEl('rect', {
                x: m.left, y: y(MAX_RANK + 0.45), width: innerW, height: y(OVERFLOW) - y(MAX_RANK + 0.45) + 6,
                class: 'viz-band',
            }, svg);
            svgEl('text', { x: m.left - 10, y: yOf(OVERFLOW) + 4, class: 'viz-axis', 'text-anchor': 'end' }, svg).textContent = `${MAX_RANK}+`;

            // Rank gridlines
            for (let r = 1; r <= MAX_RANK; r++) {
                svgEl('line', { x1: m.left, x2: m.left + innerW, y1: y(r), y2: y(r), class: 'viz-grid' }, svg);
                if (!narrow || r === 1 || r % 5 === 0) {
                    svgEl('text', { x: m.left - 10, y: y(r) + 4, class: 'viz-axis', 'text-anchor': 'end' }, svg).textContent = `#${r}`;
                }
            }

            // X axis: battles
            const { ticks } = niceTicks(battles[0], battles[battles.length - 1], narrow ? 3 : 5);
            const lastX = x(battles[battles.length - 1]);
            ticks.filter((t) => t >= battles[0] && t <= battles[battles.length - 1] && (narrow || x(t) < lastX - 70)).forEach((t) => {
                const tx = x(t);
                svgEl('text', { x: tx, y: height - 10, class: 'viz-axis', 'text-anchor': 'middle' }, svg).textContent = compact(t);
            });
            svgEl('text', { x: m.left + innerW, y: height - 10, class: 'viz-axis viz-axis--title', 'text-anchor': 'end' }, svg)
                .textContent = narrow ? '' : 'battles →';

            // Lines: S-curves between samples
            const lineGroup = svgEl('g', { class: 'race-lines' }, svg);
            const paths = new Map();
            const drawn = battles.map((_, i) => i).filter((i) => keep[i]);
            series.forEach((s) => {
                let d = '';
                drawn.forEach((i, k) => {
                    const px = x(battles[i]);
                    const py = yOf(s.ranks[i]);
                    if (k === 0) {
                        d = `M${px},${py}`;
                    } else {
                        const prevX = x(battles[drawn[k - 1]]);
                        const prevY = yOf(s.ranks[drawn[k - 1]]);
                        const mid = (prevX + px) / 2;
                        d += ` C${mid},${prevY} ${mid},${py} ${px},${py}`;
                    }
                });
                const g = svgEl('g', { class: 'race-series' }, lineGroup);
                svgEl('path', { d, fill: 'none', stroke: s.color, 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', class: 'race-line' }, g);

                // End marker and label
                const last = s.ranks.length - 1;
                const ex = x(battles[last]);
                const ey = yOf(s.ranks[last]);
                svgEl('circle', { cx: ex, cy: ey, r: 4, fill: s.color, stroke: SURFACE, 'stroke-width': 2 }, g);

                const link = svgEl('a', { href: `pokemon.php?id=${s.id}`, class: 'race-label' }, g);
                const size = narrow ? 22 : 30;
                svgEl('image', { href: s.sprite, x: ex + 7, y: ey - size / 2, width: size, height: size }, link);
                if (!narrow) {
                    svgEl('text', { x: ex + 42, y: ey + 4, class: 'viz-label' }, link).textContent = s.label;
                }
                paths.set(s.id, g);
            });

            setFocus = (id) => {
                focused = id;
                paths.forEach((g, sid) => {
                    g.classList.toggle('is-dim', id !== null && sid !== id);
                    g.classList.toggle('is-focus', sid === id);
                });
                legend?.querySelectorAll('.legend__item').forEach((btn, i) => {
                    btn.classList.toggle('is-dim', id !== null && series[i].id !== id);
                });
            };
            if (focused !== null) setFocus(focused);

            // Crosshair + tooltip
            const cross = svgEl('g', { class: 'viz-cross', visibility: 'hidden' }, svg);
            const crossLine = svgEl('line', { y1: m.top, y2: m.top + innerH, class: 'viz-cross__line' }, cross);
            const crossDots = series.map((s) => svgEl('circle', { r: 4, fill: s.color, stroke: SURFACE, 'stroke-width': 2 }, cross));

            const hit = svgEl('rect', { x: m.left, y: m.top, width: innerW, height: innerH, fill: 'transparent', class: 'viz-hit' }, svg);

            const onMove = (event) => {
                const rect = svg.getBoundingClientRect();
                const px = event.clientX - rect.left;
                let idx = 0;
                let best = Infinity;
                battles.forEach((b, i) => {
                    if (!keep[i]) return;
                    const dist = Math.abs(x(b) - px);
                    if (dist < best) {
                        best = dist;
                        idx = i;
                    }
                });
                const cx = x(battles[idx]);
                crossLine.setAttribute('x1', cx);
                crossLine.setAttribute('x2', cx);
                series.forEach((s, i) => {
                    crossDots[i].setAttribute('cx', cx);
                    crossDots[i].setAttribute('cy', yOf(s.ranks[idx]));
                });
                cross.setAttribute('visibility', 'visible');

                const point = data.points[idx];
                showTip(event, (t) => {
                    tipHeader(t, `Battle ${fmt.format(point.battle)} · ${formatDate(point.date)}`);
                    [...series]
                        .sort((a, b) => a.ranks[idx] - b.ranks[idx])
                        .forEach((s) => tipRow(t, {
                            color: s.color,
                            value: `#${fmt.format(s.ranks[idx])}`,
                            label: s.label,
                            sub: fmt.format(s.elos[idx]),
                        }));
                });
            };

            hit.addEventListener('pointermove', onMove);
            hit.addEventListener('pointerdown', onMove);
            hit.addEventListener('pointerleave', () => {
                cross.setAttribute('visibility', 'hidden');
                hideTip();
            });
        });
    };

    /* ---------------------------------------------------------------------
       Generation showdown (beeswarm)
       --------------------------------------------------------------------- */

    const renderSwarm = () => {
        const container = document.getElementById('swarm-chart');
        const raw = readJson('field-data');
        if (!container || !raw || !raw.length) return;

        const roman = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
        const field = raw.map(([id, name, gen, elo, sprite]) => ({ id, name: titleCase(name), gen, elo, sprite }));
        const gens = [...new Set(field.map((p) => p.gen))].sort((a, b) => a - b);

        const median = (values) => {
            const s = [...values].sort((a, b) => a - b);
            const mid = Math.floor(s.length / 2);
            return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
        };

        mount(container, (width) => {
            const narrow = isNarrow(width);
            const height = narrow ? 460 : 460;
            const m = { top: 16, right: 8, bottom: 52, left: narrow ? 40 : 48 };
            const innerW = width - m.left - m.right;
            const innerH = height - m.top - m.bottom;
            // Dense columns on phones: shrink dots so they overlap less
            const r = narrow && innerW / gens.length < 120 ? 3 : 4;
            const gap = 1;

            const elos = field.map((p) => p.elo);
            const minElo = Math.min(...elos);
            const maxElo = Math.max(...elos);
            const pad = Math.max(10, (maxElo - minElo) * 0.06);
            const y = scale(minElo - pad, maxElo + pad, m.top + innerH, m.top);
            const colW = innerW / gens.length;

            const svg = svgEl('svg', { width, height, viewBox: `0 0 ${width} ${height}`, class: 'viz' }, container);

            // Y gridlines
            niceTicks(minElo - pad, maxElo + pad, narrow ? 4 : 6).ticks.forEach((t) => {
                if (t < minElo - pad || t > maxElo + pad) return;
                svgEl('line', { x1: m.left, x2: m.left + innerW, y1: y(t), y2: y(t), class: 'viz-grid' }, svg);
                svgEl('text', { x: m.left - 10, y: y(t) + 4, class: 'viz-axis', 'text-anchor': 'end' }, svg).textContent = fmt.format(t);
            });

            const dots = [];
            const dotLayer = svgEl('g', {}, svg);
            const overlay = svgEl('g', {}, svg);

            gens.forEach((gen, gi) => {
                const members = field.filter((p) => p.gen === gen).sort((a, b) => b.elo - a.elo);
                const cx = m.left + colW * gi + colW / 2;
                const half = colW / 2 - r - 4;
                const placed = [];
                const minDist = 2 * r + gap;

                // Greedy beeswarm: nudge each dot sideways until it clears its neighbours
                members.forEach((p) => {
                    const py = y(p.elo);
                    let px = cx;
                    for (let step = 0; step <= half * 2; step++) {
                        const offset = Math.ceil(step / 2) * (step % 2 ? 1 : -1) * 2;
                        if (Math.abs(offset) > half) {
                            px = cx + (Math.random() - 0.5) * 2 * half;
                            break;
                        }
                        const candidate = cx + offset;
                        const clear = placed.every((q) => (q.x - candidate) ** 2 + (q.y - py) ** 2 >= minDist ** 2);
                        if (clear) {
                            px = candidate;
                            break;
                        }
                    }
                    const dot = { ...p, x: px, y: py };
                    placed.push(dot);
                    dots.push(dot);
                    svgEl('circle', { cx: px, cy: py, r, class: 'swarm-dot' }, dotLayer);
                });

                // Median marker
                const med = median(members.map((p) => p.elo));
                const mw = Math.min(colW * 0.7, 120);
                svgEl('line', {
                    x1: cx - mw / 2, x2: cx + mw / 2, y1: y(med), y2: y(med),
                    class: 'swarm-median',
                }, overlay);

                // Column labels
                svgEl('text', { x: cx, y: height - 28, class: 'viz-label', 'text-anchor': 'middle' }, svg).textContent = narrow ? roman[gen] || gen : `Gen ${roman[gen] || gen}`;
                svgEl('text', { x: cx, y: height - 10, class: 'viz-axis', 'text-anchor': 'middle' }, svg).textContent = narrow ? fmt.format(Math.round(med)) : `median ${fmt.format(Math.round(med))}`;

                if (gi > 0) {
                    svgEl('line', { x1: m.left + colW * gi, x2: m.left + colW * gi, y1: m.top, y2: m.top + innerH, class: 'viz-grid viz-grid--col' }, svg);
                }
            });

            const ring = svgEl('circle', { r: 7, class: 'swarm-hover', visibility: 'hidden' }, svg);
            const hit = svgEl('rect', { x: m.left, y: m.top, width: innerW, height: innerH, fill: 'transparent', class: 'viz-hit viz-hit--link' }, svg);
            let current = null;

            const nearest = (event) => {
                const rect = svg.getBoundingClientRect();
                const px = event.clientX - rect.left;
                const py = event.clientY - rect.top;
                let best = null;
                let bestDist = 24 ** 2;
                dots.forEach((d) => {
                    const dist = (d.x - px) ** 2 + (d.y - py) ** 2;
                    if (dist < bestDist) {
                        bestDist = dist;
                        best = d;
                    }
                });
                return best;
            };

            const onMove = (event) => {
                current = nearest(event);
                if (!current) {
                    ring.setAttribute('visibility', 'hidden');
                    hit.style.cursor = 'default';
                    hideTip();
                    return;
                }
                hit.style.cursor = 'pointer';
                ring.setAttribute('cx', current.x);
                ring.setAttribute('cy', current.y);
                ring.setAttribute('visibility', 'visible');
                const d = current;
                showTip(event, (t) => {
                    const head = htmlEl('div', 'viz-tip__mon', null, t);
                    const img = htmlEl('img', null, null, head);
                    img.src = d.sprite;
                    img.alt = '';
                    const text = htmlEl('div', null, null, head);
                    htmlEl('div', 'viz-tip__value', fmt.format(d.elo), text);
                    htmlEl('div', 'viz-tip__label', d.name, text);
                    htmlEl('div', 'viz-tip__sub', `#${String(d.id).padStart(3, '0')} · Gen ${roman[d.gen] || d.gen}`, text);
                });
            };

            hit.addEventListener('pointermove', onMove);
            hit.addEventListener('pointerleave', () => {
                ring.setAttribute('visibility', 'hidden');
                hideTip();
            });
            hit.addEventListener('click', (event) => {
                const d = nearest(event);
                if (d) window.location.href = `pokemon.php?id=${d.id}`;
            });
        });
    };

    /* ---------------------------------------------------------------------
       Elo over time (single series line)
       --------------------------------------------------------------------- */

    const renderEloLine = () => {
        const container = document.getElementById('elo-chart');
        const data = readJson('elo-data');
        if (!container || !data || data.points.length < 2) return;

        const pts = data.points;

        mount(container, (width) => {
            const narrow = isNarrow(width);
            const height = narrow ? 240 : 300;
            const m = { top: 16, right: narrow ? 48 : 64, bottom: 32, left: 48 };
            const innerW = width - m.left - m.right;
            const innerH = height - m.top - m.bottom;

            const values = pts.map((p) => p.elo).concat(data.start);
            const lo = Math.min(...values);
            const hi = Math.max(...values);
            const pad = Math.max(8, (hi - lo) * 0.12);
            const x = scale(0, pts[pts.length - 1].n, m.left, m.left + innerW);
            const y = scale(lo - pad, hi + pad, m.top + innerH, m.top);

            const svg = svgEl('svg', { width, height, viewBox: `0 0 ${width} ${height}`, class: 'viz' }, container);

            const defs = svgEl('defs', {}, svg);
            const grad = svgEl('linearGradient', { id: 'eloWash', x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
            svgEl('stop', { offset: '0', 'stop-color': ACCENT, 'stop-opacity': 0.16 }, grad);
            svgEl('stop', { offset: '1', 'stop-color': ACCENT, 'stop-opacity': 0 }, grad);

            niceTicks(lo - pad, hi + pad, narrow ? 3 : 4).ticks.forEach((t) => {
                if (t < lo - pad || t > hi + pad) return;
                svgEl('line', { x1: m.left, x2: m.left + innerW, y1: y(t), y2: y(t), class: 'viz-grid' }, svg);
                svgEl('text', { x: m.left - 10, y: y(t) + 4, class: 'viz-axis', 'text-anchor': 'end' }, svg).textContent = fmt.format(t);
            });

            // Starting rating reference
            svgEl('line', { x1: m.left, x2: m.left + innerW, y1: y(data.start), y2: y(data.start), class: 'viz-baseline' }, svg);

            const last = pts[pts.length - 1].n;
            niceTicks(0, last, narrow ? 3 : 5).ticks.filter((t) => t <= last && (narrow || x(t) < m.left + innerW - 70)).forEach((t) => {
                svgEl('text', { x: x(t), y: height - 10, class: 'viz-axis', 'text-anchor': 'middle' }, svg).textContent = compact(t);
            });
            if (!narrow) {
                svgEl('text', { x: m.left + innerW, y: height - 10, class: 'viz-axis viz-axis--title', 'text-anchor': 'end' }, svg).textContent = 'battles →';
            }

            const line = pts.map((p, i) => `${i ? 'L' : 'M'}${x(p.n)},${y(p.elo)}`).join(' ');
            svgEl('path', { d: `${line} L${x(last)},${m.top + innerH} L${x(0)},${m.top + innerH} Z`, fill: 'url(#eloWash)' }, svg);
            svgEl('path', { d: line, fill: 'none', stroke: ACCENT, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round', class: 'elo-line' }, svg);

            const end = pts[pts.length - 1];
            svgEl('circle', { cx: x(end.n), cy: y(end.elo), r: 4, fill: ACCENT, stroke: SURFACE, 'stroke-width': 2 }, svg);
            svgEl('text', { x: x(end.n) + 10, y: y(end.elo) + 4, class: 'viz-label viz-label--strong' }, svg).textContent = fmt.format(end.elo);

            const cross = svgEl('g', { visibility: 'hidden' }, svg);
            const crossLine = svgEl('line', { y1: m.top, y2: m.top + innerH, class: 'viz-cross__line' }, cross);
            const crossDot = svgEl('circle', { r: 4, fill: ACCENT, stroke: SURFACE, 'stroke-width': 2 }, cross);
            const hit = svgEl('rect', { x: m.left, y: m.top, width: innerW, height: innerH, fill: 'transparent', class: 'viz-hit' }, svg);

            const onMove = (event) => {
                const rect = svg.getBoundingClientRect();
                const px = event.clientX - rect.left;
                const n = Math.max(0, Math.min(last, Math.round(((px - m.left) / innerW) * last)));
                const p = pts.reduce((a, b) => (Math.abs(b.n - n) < Math.abs(a.n - n) ? b : a));
                crossLine.setAttribute('x1', x(p.n));
                crossLine.setAttribute('x2', x(p.n));
                crossDot.setAttribute('cx', x(p.n));
                crossDot.setAttribute('cy', y(p.elo));
                cross.setAttribute('visibility', 'visible');

                showTip(event, (t) => {
                    tipHeader(t, p.n === 0 ? 'Debut' : `Battle ${fmt.format(p.n)} · ${formatDate(p.date)}`);
                    const change = p.delta === null || p.delta === undefined ? '' : ` · ${p.delta > 0 ? '+' : p.delta < 0 ? '−' : '±'}${Math.abs(p.delta)}`;
                    tipRow(t, {
                        value: fmt.format(p.elo),
                        label: p.n === 0 ? 'Starting rating' : `${p.won ? 'Beat' : 'Lost to'} ${p.opp}${change}`,
                    });
                });
            };

            hit.addEventListener('pointermove', onMove);
            hit.addEventListener('pointerdown', onMove);
            hit.addEventListener('pointerleave', () => {
                cross.setAttribute('visibility', 'hidden');
                hideTip();
            });
        });
    };

    renderRace();
    renderSwarm();
    renderEloLine();
})();
