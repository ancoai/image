const RESIZE_DEBOUNCE = 120;

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

const debounce = (fn, wait) => {
    let timer = null;
    return (...args) => {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(() => {
            timer = null;
            fn(...args);
        }, wait);
    };
};

class PuzzleBoard {
    constructor(canvas, options) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.image = new Image();
        this.image.crossOrigin = 'anonymous';
        this.cols = options.cols;
        this.rows = options.rows;
        this.minimumSnap = options.minimumSnap ?? 6;
        const requestedSnap = typeof options.snapDistance === 'number' ? options.snapDistance : 20;
        this.snapDistance = clamp(requestedSnap, this.minimumSnap, 220);
        this.backgroundOpacity = clamp(options.backgroundOpacity ?? 0.35, 0, 1);
        this.defaultGhostOpacity = this.backgroundOpacity;
        this.lastVisibleOpacity = this.backgroundOpacity;
        this.onComplete = options.onComplete ?? (() => {});
        this.onProgress = options.onProgress ?? (() => {});
        this.onError = options.onError ?? (() => {});
        this.onShuffle = options.onShuffle ?? (() => {});
        this.pieces = [];
        this.draggingPiece = null;
        this.offsetX = 0;
        this.offsetY = 0;
        this.completed = false;
        this.loaded = false;
        this.errorMessage = '';
        this.peekTimer = null;
        this.peekActive = false;
        this.ghostVisible = this.backgroundOpacity > 0;
        this.startedAt = null;
        this.completedAt = null;
        this.progressCache = { placed: 0, total: 0 };
        this.image.addEventListener('load', () => this.setup());
        this.image.addEventListener('error', () => this.handleError('图片加载失败，请稍后重试。'));
        this.image.src = options.imageUrl;
        this._resizeHandler = debounce(() => this.resize(), RESIZE_DEBOUNCE);
        window.addEventListener('resize', this._resizeHandler);
    }

    setup() {
        this.loaded = true;
        this.baseWidth = this.image.width;
        this.baseHeight = this.image.height;
        if (!this.baseWidth || !this.baseHeight) {
            this.handleError('无法解析图片尺寸。');
            return;
        }
        this.resize();
    }

    handleError(message) {
        this.errorMessage = message;
        this.onError(message);
        this.draw();
    }

    resize() {
        if (!this.loaded || this.errorMessage) {
            return;
        }
        const parentWidth = this.canvas.parentElement ? this.canvas.parentElement.clientWidth : this.baseWidth;
        const scale = clamp(parentWidth / this.baseWidth, 0.1, 1);
        const prevWidth = this.canvas.width || this.baseWidth;
        const prevHeight = this.canvas.height || this.baseHeight;
        this.canvas.width = Math.max(1, Math.round(this.baseWidth * scale));
        this.canvas.height = Math.max(1, Math.round(this.baseHeight * scale));
        this.scale = scale;

        const ratioX = this.canvas.width / prevWidth;
        const ratioY = this.canvas.height / prevHeight;
        const pieceWidth = this.canvas.width / this.cols;
        const pieceHeight = this.canvas.height / this.rows;

        if (this.pieces.length === 0) {
            this.generatePieces(pieceWidth, pieceHeight);
        } else {
            this.pieces.forEach(piece => {
                piece.width = pieceWidth;
                piece.height = pieceHeight;
                piece.targetX = piece.col * pieceWidth;
                piece.targetY = piece.row * pieceHeight;
                piece.x *= ratioX;
                piece.y *= ratioY;
            });
        }
        this.draw();
    }

    generatePieces(pieceWidth, pieceHeight) {
        const pieces = [];
        for (let row = 0; row < this.rows; row++) {
            for (let col = 0; col < this.cols; col++) {
                const targetX = col * pieceWidth;
                const targetY = row * pieceHeight;
                const position = this.scatterPosition(targetX, targetY, pieceWidth, pieceHeight);
                pieces.push({
                    col,
                    row,
                    width: pieceWidth,
                    height: pieceHeight,
                    targetX,
                    targetY,
                    x: position.x,
                    y: position.y,
                    placed: false,
                });
            }
        }
        this.pieces = pieces;
        this.completed = false;
        this.startedAt = null;
        this.completedAt = null;
        this.progressCache.total = pieces.length;
        this.progressCache.placed = pieces.filter(piece => piece.placed).length;
        this.reportProgress();
    }

    reportProgress() {
        const placed = this.pieces.reduce((total, piece) => total + (piece.placed ? 1 : 0), 0);
        const total = this.pieces.length;
        this.progressCache = { placed, total };
        this.onProgress(placed, total);
    }

    scatterPosition(targetX, targetY, width, height) {
        const bounds = {
            width: Math.max(this.canvas.width - width, width),
            height: Math.max(this.canvas.height - height, height),
        };
        let x = 0;
        let y = 0;
        let attempts = 0;
        const minOffset = Math.min(width, height) * 0.6;
        do {
            x = Math.random() * (bounds.width - width);
            y = Math.random() * (bounds.height - height);
            attempts++;
        } while (
            attempts < 10 &&
            Math.abs(x - targetX) < minOffset &&
            Math.abs(y - targetY) < minOffset
        );
        return { x, y };
    }

    pointerDown(x, y) {
        if (this.completed || this.errorMessage) {
            return;
        }
        if (!this.startedAt) {
            this.startedAt = performance.now();
        }
        for (let i = this.pieces.length - 1; i >= 0; i--) {
            const piece = this.pieces[i];
            if (piece.placed) {
                continue;
            }
            if (x >= piece.x && x <= piece.x + piece.width && y >= piece.y && y <= piece.y + piece.height) {
                this.draggingPiece = piece;
                this.offsetX = x - piece.x;
                this.offsetY = y - piece.y;
                this.pieces.splice(i, 1);
                this.pieces.push(piece);
                break;
            }
        }
    }

    pointerMove(x, y) {
        if (!this.draggingPiece) {
            return;
        }
        const piece = this.draggingPiece;
        piece.x = clamp(x - this.offsetX, 0, this.canvas.width - piece.width);
        piece.y = clamp(y - this.offsetY, 0, this.canvas.height - piece.height);
        this.draw();
    }

    pointerUp() {
        if (!this.draggingPiece) {
            return;
        }
        const piece = this.draggingPiece;
        this.draggingPiece = null;
        const tolerance = Math.max(this.snapDistance, Math.min(piece.width, piece.height) * 0.35);
        if (Math.abs(piece.x - piece.targetX) < tolerance && Math.abs(piece.y - piece.targetY) < tolerance) {
            piece.x = piece.targetX;
            piece.y = piece.targetY;
            piece.placed = true;
        }
        this.draw();
        this.reportProgress();
        if (!this.completed && this.pieces.every(p => p.placed)) {
            this.completed = true;
            this.completedAt = performance.now();
            this.reportProgress();
            this.onComplete();
        }
    }

    draw() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        if (this.errorMessage) {
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
            this.ctx.fillStyle = '#d9534f';
            this.ctx.font = '16px sans-serif';
            this.ctx.textAlign = 'center';
            this.ctx.fillText(this.errorMessage, this.canvas.width / 2, this.canvas.height / 2);
            return;
        }
        if (!this.loaded) {
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
            this.ctx.fillStyle = '#444';
            this.ctx.font = '14px sans-serif';
            this.ctx.textAlign = 'center';
            this.ctx.fillText('图片加载中…', this.canvas.width / 2, this.canvas.height / 2);
            return;
        }
        const overlayAlpha = this.peekActive ? 1 : this.backgroundOpacity;
        this.ctx.save();
        if (overlayAlpha > 0) {
            this.ctx.globalAlpha = overlayAlpha;
            this.ctx.drawImage(this.image, 0, 0, this.canvas.width, this.canvas.height);
        }
        this.ctx.globalAlpha = 1;
        this.ctx.strokeStyle = 'rgba(0,0,0,0.15)';
        this.ctx.lineWidth = 1;
        for (let i = 1; i < this.cols; i++) {
            const x = (this.canvas.width / this.cols) * i;
            this.ctx.beginPath();
            this.ctx.moveTo(x, 0);
            this.ctx.lineTo(x, this.canvas.height);
            this.ctx.stroke();
        }
        for (let j = 1; j < this.rows; j++) {
            const y = (this.canvas.height / this.rows) * j;
            this.ctx.beginPath();
            this.ctx.moveTo(0, y);
            this.ctx.lineTo(this.canvas.width, y);
            this.ctx.stroke();
        }
        this.ctx.restore();
        this.pieces.forEach(piece => {
            this.ctx.save();
            this.ctx.beginPath();
            this.ctx.rect(piece.x, piece.y, piece.width, piece.height);
            this.ctx.clip();
            this.ctx.drawImage(
                this.image,
                piece.col * this.image.width / this.cols,
                piece.row * this.image.height / this.rows,
                this.image.width / this.cols,
                this.image.height / this.rows,
                piece.x,
                piece.y,
                piece.width,
                piece.height
            );
            this.ctx.strokeStyle = piece.placed ? 'rgba(30,144,255,0.6)' : 'rgba(0,0,0,0.45)';
            this.ctx.lineWidth = piece.placed ? 2 : 1;
            this.ctx.strokeRect(piece.x, piece.y, piece.width, piece.height);
            this.ctx.restore();
        });
    }

    setSnapDistance(distance) {
        const numeric = Number(distance);
        if (Number.isFinite(numeric)) {
            this.snapDistance = clamp(numeric, this.minimumSnap, 320);
        }
        return this.snapDistance;
    }

    setGhostOpacity(value) {
        const opacity = clamp(value, 0, 1);
        this.backgroundOpacity = opacity;
        if (opacity > 0) {
            this.lastVisibleOpacity = opacity;
            this.ghostVisible = true;
        } else {
            this.ghostVisible = false;
        }
        this.draw();
        return this.backgroundOpacity;
    }

    setGhostVisible(enabled) {
        this.ghostVisible = Boolean(enabled);
        if (this.ghostVisible) {
            const restored = this.lastVisibleOpacity > 0 ? this.lastVisibleOpacity : (this.defaultGhostOpacity || 0.35);
            this.backgroundOpacity = clamp(restored, 0, 1);
        } else {
            this.backgroundOpacity = 0;
        }
        this.draw();
        return this.backgroundOpacity;
    }

    peekOriginal(duration = 2000) {
        if (!this.loaded || this.errorMessage) {
            return;
        }
        const clamped = clamp(duration, 500, 10000);
        if (this.peekTimer) {
            clearTimeout(this.peekTimer);
        }
        this.peekActive = true;
        this.draw();
        this.peekTimer = setTimeout(() => {
            this.peekActive = false;
            this.peekTimer = null;
            this.draw();
        }, clamped);
    }

    shufflePieces(onlyUnplaced = true) {
        if (!this.loaded || this.errorMessage) {
            return;
        }
        const pieceWidth = this.canvas.width / this.cols;
        const pieceHeight = this.canvas.height / this.rows;
        this.pieces.forEach(piece => {
            piece.width = pieceWidth;
            piece.height = pieceHeight;
            piece.targetX = piece.col * pieceWidth;
            piece.targetY = piece.row * pieceHeight;
            if (!onlyUnplaced || !piece.placed) {
                const position = this.scatterPosition(piece.targetX, piece.targetY, pieceWidth, pieceHeight);
                piece.x = position.x;
                piece.y = position.y;
                piece.placed = false;
            } else {
                piece.x = piece.targetX;
                piece.y = piece.targetY;
            }
        });
        if (!onlyUnplaced) {
            this.startedAt = null;
        }
        this.completed = false;
        this.completedAt = null;
        this.reportProgress();
        this.draw();
        this.onShuffle(onlyUnplaced);
    }

    reset() {
        this.shufflePieces(false);
    }

    getElapsedSeconds() {
        if (!this.startedAt) {
            return 0;
        }
        const end = this.completedAt ?? performance.now();
        return Math.max(0, (end - this.startedAt) / 1000);
    }

    progress() {
        return { ...this.progressCache };
    }

    destroy() {
        if (this.peekTimer) {
            clearTimeout(this.peekTimer);
            this.peekTimer = null;
        }
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
            this._resizeHandler = null;
        }
    }
}

export function initPuzzle(canvas, options = {}) {
    if (!canvas || !(canvas instanceof HTMLCanvasElement)) {
        throw new Error('需要提供有效的 canvas 元素来初始化拼图。');
    }
    const board = new PuzzleBoard(canvas, options);
    const handler = evt => {
        const rect = canvas.getBoundingClientRect();
        const point = evt.touches ? evt.touches[0] : evt;
        const x = (point.clientX - rect.left) * (canvas.width / rect.width);
        const y = (point.clientY - rect.top) * (canvas.height / rect.height);
        return { x, y };
    };

    const listeners = [];
    const bind = (target, event, fn, opts) => {
        target.addEventListener(event, fn, opts);
        listeners.push(() => target.removeEventListener(event, fn, opts));
    };

    const start = evt => {
        evt.preventDefault();
        const pos = handler(evt);
        board.pointerDown(pos.x, pos.y);
    };
    const move = evt => {
        if (!board.draggingPiece) {
            return;
        }
        evt.preventDefault();
        const pos = handler(evt);
        board.pointerMove(pos.x, pos.y);
    };
    const end = evt => {
        evt.preventDefault();
        board.pointerUp();
    };

    bind(canvas, 'mousedown', start);
    bind(window, 'mousemove', move);
    bind(window, 'mouseup', end);
    bind(canvas, 'touchstart', start, { passive: false });
    bind(window, 'touchmove', move, { passive: false });
    bind(window, 'touchend', end, { passive: false });
    bind(window, 'touchcancel', end, { passive: false });

    const originalDestroy = board.destroy.bind(board);
    board.destroy = () => {
        listeners.forEach(off => off());
        listeners.length = 0;
        originalDestroy();
    };

    return board;
}
