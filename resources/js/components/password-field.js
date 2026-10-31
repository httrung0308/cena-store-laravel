export function passwordField() {
    return {
        shown: false,

        get inputType() {
            return this.shown ? 'text' : 'password';
        },

        get toggleLabel() {
            return this.shown ? 'Ẩn mật khẩu' : 'Hiện mật khẩu';
        },

        toggle() {
            this.shown = !this.shown;
        },
    };
}
