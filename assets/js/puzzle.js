class PuzzleBoard {
    constructor(canvas, options) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.image = new Image();
        this.image.crossOrigin = 'anonymous';
        this.cols = options.cols;
        this.rows = options.rows;
        this.snapDistance = options.snapDistance || 20;
        this.backgroundOpacity = options.backgroundOpacity || 0.35;
        this.onComplete = options.onComplete || (() => {});
        this.pieces = [];
        this.draggingPiece = null;
        this.offsetX = 0;
        this.offsetY = 0;
        this.completed = false;
        this.image.addEventListener('load', () => this.setup());
        this.image.src = options.imageUrl;
        window.addEventListener('resize', () => this.resize());
    }

    setup() {
        this.baseWidth = this.image.width;
        this.baseHeight = this.image.height;
        this.resize();
    }

    resize() {
        const maxWidth = this.canvas.parentElement.clientWidth;
        const scale = Math.min(maxWidth / this.baseWidth, 1);
        this.canvas.width = this.baseWidth * scale;
        this.canvas.height = this.baseHeight * scale;
        this.scale = scale;
        this.generatePieces();
        this.draw();
    }

    generatePieces() {
        const pieceWidth = this.canvas.width / this.cols;
        const pieceHeight = this.canvas.height / this.rows;
        this.pieces = [];
        for (let row = 0; row < this.rows; row++) {
            for (let col = 0; col < this.cols; col++) {
                const targetX = col * pieceWidth;
                const targetY = row * pieceHeight;
                const piece = {
                    col,
                    row,
                    width: pieceWidth,
                    height: pieceHeight,
                    targetX,
                    targetY,
                    x: Math.random() * (this.canvas.width - pieceWidth),
                    y: Math.random() * (this.canvas.height - pieceHeight),
                    placed: false,
                };
                this.pieces.push(piece);
            }
        }
    }

    pointerDown(x, y) {
        if (this.completed) {
            return;
        }
        for (let i = this.pieces.length - 1; i >= 0; i--) {
            const piece = this.pieces[i];
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
        this.draggingPiece.x = x - this.offsetX;
        this.draggingPiece.y = y - this.offsetY;
        this.draw();
    }

    pointerUp() {
        if (!this.draggingPiece) {
            return;
        }
        const piece = this.draggingPiece;
        this.draggingPiece = null;
        if (Math.abs(piece.x - piece.targetX) < this.snapDistance && Math.abs(piece.y - piece.targetY) < this.snapDistance) {
            piece.x = piece.targetX;
            piece.y = piece.targetY;
            piece.placed = true;
        }
        this.draw();
        if (this.pieces.every(p => p.placed)) {
            this.completed = true;
            this.onComplete();
        }
    }

    draw() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.globalAlpha = this.backgroundOpacity;
        this.ctx.drawImage(this.image, 0, 0, this.canvas.width, this.canvas.height);
        this.ctx.globalAlpha = 1;
        this.ctx.strokeStyle = 'rgba(0,0,0,0.3)';
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
            this.ctx.strokeStyle = 'rgba(0,0,0,0.5)';
            this.ctx.strokeRect(piece.x, piece.y, piece.width, piece.height);
            this.ctx.restore();
        });
    }
}

export function initPuzzle(canvas, options) {
    const board = new PuzzleBoard(canvas, options);
    const handler = evt => {
        const rect = canvas.getBoundingClientRect();
        const x = (evt.clientX - rect.left) * (canvas.width / rect.width);
        const y = (evt.clientY - rect.top) * (canvas.height / rect.height);
        return { x, y };
    };

    const start = evt => {
        evt.preventDefault();
        const pos = handler(evt.touches ? evt.touches[0] : evt);
        board.pointerDown(pos.x, pos.y);
    };
    const move = evt => {
        if (!board.draggingPiece) return;
        evt.preventDefault();
        const pos = handler(evt.touches ? evt.touches[0] : evt);
        board.pointerMove(pos.x, pos.y);
    };
    const end = evt => {
        evt.preventDefault();
        board.pointerUp();
    };

    canvas.addEventListener('mousedown', start);
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    window.addEventListener('touchmove', move, { passive: false });
    window.addEventListener('touchend', end, { passive: false });

    return board;
}
