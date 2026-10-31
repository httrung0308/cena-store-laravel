import { useState } from 'react';

import { motion } from 'framer-motion';
import Link from 'next/link';
import { useRouter } from 'next/router';

import { authService } from '@/common/api/services/authService';

const slideAnimation = {
  from: {
    opacity: 0,
    transform: 'translateY(-50px)',
  },
  to: {
    opacity: 1,
    transform: 'translateY(0)',
    transition: {
      duration: 0.8,
      ease: [0.6, 0.01, -0.05, 0.9],
    },
  },
};

const ForgotPassword = () => {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [emailError, setEmailError] = useState('');
  const [emailFormError, setEmailFormError] = useState('');
  const [emailSuccess, setEmailSuccess] = useState('');
  const [emailSubmitting, setEmailSubmitting] = useState(false);
  const [showCodeForm, setShowCodeForm] = useState(false);

  // Code verification fields
  const [code, setCode] = useState('');
  const [codeError, setCodeError] = useState('');
  const [codeFormError, setCodeFormError] = useState('');
  const [codeSubmitting, setCodeSubmitting] = useState(false);

  const validateEmail = (email: string) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  const handleEmailSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setEmailError('');
    setEmailFormError('');
    setEmailSuccess('');

    if (!email) {
      setEmailError('Required');
      return;
    }

    if (!validateEmail(email)) {
      setEmailError('Invalid email');
      return;
    }

    setEmailSubmitting(true);

    try {
      await authService.forgotPassword({ email });
      setEmailSuccess(
        'A reset code has been sent to your email address.',
      );
      setShowCodeForm(true);
    } catch (error: any) {
      const errorMessage =
        error?.message || 'An error occurred. Please try again.';
      setEmailFormError(errorMessage);
    } finally {
      setEmailSubmitting(false);
    }
  };

  const handleCodeSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setCodeError('');
    setCodeFormError('');

    if (!code) {
      setCodeError('Required');
      return;
    }

    if (code.length !== 6) {
      setCodeError('Code must be 6 digits');
      return;
    }

    setCodeSubmitting(true);

    try {
      await authService.verifyResetCode(email, code);

      // Success - redirect to reset password page with email and code
      router.push({
        pathname: '/reset-password',
        query: { email, code },
      });
    } catch (error: any) {
      let errorMessage = error?.message || 'Invalid or expired code. Please try again.';

      if (errorMessage.includes('Invalid email or password')) {
        errorMessage = 'Invalid or expired code. Please try again.';
      }

      setCodeFormError(errorMessage);
    } finally {
      setCodeSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen flex-col items-center justify-center">
      <motion.div
        variants={slideAnimation}
        initial="from"
        animate="to"
        className="-mt-24 w-full p-4 sm:mt-0 sm:p-7 2xl:-mt-36"
      >
        <h1 className="text-center text-4xl font-semibold leading-tight xl:text-6xl">
          Forgot Password?
        </h1>
        <p className="mt-4 text-center text-gray-600">
          Enter your email address to reset your password.
        </p>

        <div className="mt-4 w-full text-center">
          <Link href="/login">
            <a className="underline">Remember your password?</a>
          </Link>
        </div>

        <div className="mt-10">
          <div className="flex w-full justify-center">
            <form className="flex w-160 flex-col gap-1" onSubmit={handleEmailSubmit}>
              <label className="flex flex-col">
                <span className="text-lg font-semibold">Email</span>
                <input
                  type="email"
                  className="input"
                  placeholder="Enter your email..."
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  disabled={emailSubmitting || showCodeForm}
                />
                {emailError && (
                  <div className="h-4 text-xs italic text-red-500">{emailError}</div>
                )}
              </label>

              {emailSuccess && (
                <div className="rounded-lg bg-green-50 p-4 text-center text-sm text-green-700">
                  <svg
                    className="mx-auto mb-2 h-8 w-8 text-green-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                    />
                  </svg>
                  {emailSuccess}
                </div>
              )}
              {emailFormError && (
                <div className="text-center text-xs italic text-red-500">{emailFormError}</div>
              )}

              {!showCodeForm && (
                <button
                  type="submit"
                  className="btn mt-1 h-10 rounded-md py-2 disabled:cursor-not-allowed disabled:opacity-50"
                  disabled={emailSubmitting}
                >
                  {emailSubmitting ? 'Sending...' : 'Send Reset Code'}
                </button>
              )}
            </form>
          </div>

          {showCodeForm && (
            <div className="mt-8 flex w-full justify-center">
              <form className="flex w-160 flex-col gap-1" onSubmit={handleCodeSubmit}>
                <label className="flex flex-col">
                  <span className="text-lg font-semibold">Verification Code</span>
                  <input
                    type="text"
                    className="input text-center text-2xl tracking-widest"
                    placeholder="000000"
                    value={code}
                    onChange={(e) => {
                      const value = e.target.value.replace(/\D/g, '').slice(0, 6);
                      setCode(value);
                    }}
                    disabled={codeSubmitting}
                    maxLength={6}
                  />
                  {codeError && (
                    <div className="h-4 text-xs italic text-red-500">{codeError}</div>
                  )}
                </label>

                {codeFormError && (
                  <div className="text-center text-xs italic text-red-500">{codeFormError}</div>
                )}

                <button
                  type="submit"
                  className="btn mt-1 h-10 rounded-md py-2 disabled:cursor-not-allowed disabled:opacity-50"
                  disabled={codeSubmitting}
                >
                  {codeSubmitting ? 'Verifying...' : 'Verify Code'}
                </button>
              </form>
            </div>
          )}
        </div>
      </motion.div>
    </div>
  );
};

export default ForgotPassword;
