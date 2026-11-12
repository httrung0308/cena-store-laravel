export function articleFeedback({ action = '', initialAnswer = null, initialHelpfulCount = 0, initialSaved = false } = {}) {
    return {
        action,
        myFeedback: initialAnswer,
        helpfulCount: Number.isInteger(initialHelpfulCount) ? initialHelpfulCount : 0,
        saved: Boolean(initialSaved),
        busy: false,
        pendingChoice: null,
        errorMessage: '',

        get statusText() {
            if (this.saved) {
                return 'Cảm ơn bạn đã góp ý!';
            }
            if (this.myFeedback !== null) {
                return 'Bạn đã trả lời. Bấm nút còn lại nếu muốn đổi ý.';
            }
            return '';
        },

        async submit(choice) {
            if (this.busy || !this.action) return;
            if (choice !== 'yes' && choice !== 'no') return;

            this.busy = true;
            this.pendingChoice = choice;
            this.errorMessage = '';

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ helpful: choice }),
                });

                let body = null;
                try {
                    body = await response.json();
                } catch {
                    body = null;
                }

                if (!response.ok) {
                    if (response.status === 419) {
                        this.errorMessage = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.';
                    } else if (response.status === 429) {
                        this.errorMessage = 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ít giây.';
                    } else if (response.status === 404) {
                        this.errorMessage = 'Bài viết không còn khả dụng để nhận phản hồi.';
                    } else {
                        this.errorMessage = body?.message || 'Không thể ghi nhận phản hồi. Vui lòng thử lại.';
                    }
                    return;
                }

                this.myFeedback = typeof body?.my_feedback === 'boolean' ? body.my_feedback : (choice === 'yes');
                if (Number.isInteger(body?.helpful_count)) {
                    this.helpfulCount = body.helpful_count;
                }
                this.saved = true;
            } catch {
                this.errorMessage = 'Không kết nối được máy chủ. Vui lòng thử lại.';
            } finally {
                this.busy = false;
                this.pendingChoice = null;
            }
        },
    };
}
