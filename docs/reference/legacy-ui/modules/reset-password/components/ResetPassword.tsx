import { useState, useEffect } from 'react';

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

const ResetPassword = () => {
  const router = useRouter();
  const { email: emailParam, code: codeParam } = router.query;

  const [email, setEmail] = useState('');
  const [code, setCode] = useState('');
  const [missingParams, setMissingParams] = useState(false);

  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [passwordError, setPasswordError] = useState('');
  const [confirmPasswordError, setConfirmPasswordError] = useState('');
  const [formError, setFormError] = useState('');
  const [success, setSuccess] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  useEffect(() => {
    if (router.isReady) {
      if (emailParam && codeParam) {
        const emailStr = Array.isArray(emailParam) ? emailParam[0] : emailParam;
        const codeStr = Array.isArray(codeParam) ? codeParam[0] : codeParam;

        setEmail(emailStr);
        setCode(codeStr);
        setMissingParams(false);
      } else {
        setMissingParams(true);
      }
    }
  }, [router.isReady, emailParam, codeParam]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setPasswordError('');
    setConfirmPasswordError('');
    setFormError('');

    let hasError = false;

    if (!password) {
      setPasswordError('Required');
      hasError = true;
    } else if (password.length < 8) {
      setPasswordError('Password must be at least 8 characters');
      hasError = true;
    }

    if (!confirmPassword) {
      setConfirmPasswordError('Required');
      hasError = true;
    } else if (password !== confirmPassword) {
      setConfirmPasswordError('Passwords do not match');
      hasError = true;
    }

    if (hasError) return;

    setSubmitting(true);

    try {
      await authService.resetPassword({
        email,
        code,
        newPassword: password,
      });

      setSuccess(true);
      setTimeout(() => {
        router.push('/login');
      }, 3000);
    } catch (error: any) {
      const errorMessage =
        error?.message || 'An error occurred. Please try again.';
      setFormError(errorMessage);
    } finally {
      setSubmitting(false);
    }
  };

  if (missingParams) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center">
        <motion.div
          variants={slideAnimation}
          initial="from"
          animate="to"
          className="-mt-24 w-full p-4 text-center sm:mt-0 sm:p-7 2xl:-mt-36"
        >
          <div className="flex flex-col items-center gap-4">
            <svg
              className="h-16 w-16 text-red-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <h1 className="text-2xl font-semibold text-gray-800">Invalid Link</h1>
            <p className="max-w-md text-gray-600">
              The password reset link is invalid. Please request a new password reset.
            </p>
            <div className="mt-4 flex gap-4">
              <Link href="/forgot-password">
                <a className="btn rounded-md px-6 py-2">Request New Link</a>
              </Link>
              <Link href="/login">
                <a className="btn-secondary rounded-md px-6 py-2">Back to Login</a>
              </Link>
            </div>
          </div>
        </motion.div>
      </div>
    );
  }

  if (success) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center">
        <motion.div
          variants={slideAnimation}
          initial="from"
          animate="to"
          className="-mt-24 w-full p-4 text-center sm:mt-0 sm:p-7 2xl:-mt-36"
        >
          <div className="flex flex-col items-center gap-4">
            <svg
              className="h-16 w-16 text-green-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <h1 className="text-2xl font-semibold text-gray-800">
              Password Reset Successful!
            </h1>
            <p className="text-gray-600">
              Your password has been updated. Redirecting to login page...
            </p>
          </div>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center">
      <motion.div
        variants={slideAnimation}
        initial="from"
        animate="to"
        className="-mt-24 w-full p-4 sm:mt-0 sm:p-7 2xl:-mt-36"
      >
        <h1 className="text-center text-4xl font-semibold leading-tight xl:text-6xl">
          Create New Password
        </h1>
        <p className="mt-4 text-center text-gray-600">
          Enter your new password below
        </p>

        <div className="mt-10">
          <div className="flex w-full justify-center">
            <form className="flex w-160 flex-col gap-1" onSubmit={handleSubmit}>
              <label className="flex flex-col">
                <span className="text-lg font-semibold">New Password</span>
                <div className="relative w-full">
                  <input
                    type={showPassword ? 'text' : 'password'}
                    className="input w-full"
                    placeholder="Enter new password..."
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    disabled={submitting}
                    style={{ paddingRight: '2.5rem' }}
                  />
                  <button
                    className="btn-icon absolute right-0 h-full px-2"
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                  >
                    <svg
                      stroke="currentColor"
                      fill="currentColor"
                      strokeWidth="0"
                      viewBox="0 0 1024 1024"
                      height="1em"
                      width="1em"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      {showPassword ? (
                        <path d="M942.2 486.2Q889.47 375.11 816.7 296l-227.1 227.1A176.09 176.09 0 0 1 512 552q-90.67 0-155.39-64.73T291.88 332q0-82.69 53.5-145.84L149.6 390.4a60.29 60.29 0 0 1-85.2 0 60.29 60.29 0 0 1 0-85.2l714.1-714.1a60.29 60.29 0 0 1 85.2 0 60.29 60.29 0 0 1 0 85.2L539 1l2.5 2.5C723.8 191.1 857.5 325.9 942.2 486.2zM878.43 165.1L165.1 878.43l-.01-.02.01.02a60.29 60.29 0 0 1-85.19 0 60.29 60.29 0 0 1 0-85.19L793.24 79.91a60.29 60.29 0 0 1 85.19 0 60.29 60.29 0 0 1 0 85.19z"></path>
                      ) : (
                        <path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3a60.3 60.3 0 0 0 0 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM512 766c-161.3 0-279.4-81.8-362.7-254C232.6 339.8 350.7 258 512 258c161.3 0 279.4 81.8 362.7 254C791.5 684.2 673.4 766 512 766zm-4-430c-97.2 0-176 78.8-176 176s78.8 176 176 176 176-78.8 176-176-78.8-176-176-176zm0 288c-61.9 0-112-50.1-112-112s50.1-112 112-112 112 50.1 112 112-50.1 112-112 112z"></path>
                      )}
                    </svg>
                  </button>
                </div>
                {passwordError && (
                  <div className="h-4 text-xs italic text-red-500">{passwordError}</div>
                )}
              </label>

              <label className="flex flex-col">
                <span className="text-lg font-semibold">Confirm Password</span>
                <div className="relative w-full">
                  <input
                    type={showConfirmPassword ? 'text' : 'password'}
                    className="input w-full"
                    placeholder="Confirm new password..."
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    disabled={submitting}
                    style={{ paddingRight: '2.5rem' }}
                  />
                  <button
                    className="btn-icon absolute right-0 h-full px-2"
                    type="button"
                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  >
                    <svg
                      stroke="currentColor"
                      fill="currentColor"
                      strokeWidth="0"
                      viewBox="0 0 1024 1024"
                      height="1em"
                      width="1em"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      {showConfirmPassword ? (
                        <path d="M942.2 486.2Q889.47 375.11 816.7 296l-227.1 227.1A176.09 176.09 0 0 1 512 552q-90.67 0-155.39-64.73T291.88 332q0-82.69 53.5-145.84L149.6 390.4a60.29 60.29 0 0 1-85.2 0 60.29 60.29 0 0 1 0-85.2l714.1-714.1a60.29 60.29 0 0 1 85.2 0 60.29 60.29 0 0 1 0 85.2L539 1l2.5 2.5C723.8 191.1 857.5 325.9 942.2 486.2zM878.43 165.1L165.1 878.43l-.01-.02.01.02a60.29 60.29 0 0 1-85.19 0 60.29 60.29 0 0 1 0-85.19L793.24 79.91a60.29 60.29 0 0 1 85.19 0 60.29 60.29 0 0 1 0 85.19z"></path>
                      ) : (
                        <path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3a60.3 60.3 0 0 0 0 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM512 766c-161.3 0-279.4-81.8-362.7-254C232.6 339.8 350.7 258 512 258c161.3 0 279.4 81.8 362.7 254C791.5 684.2 673.4 766 512 766zm-4-430c-97.2 0-176 78.8-176 176s78.8 176 176 176 176-78.8 176-176-78.8-176-176-176zm0 288c-61.9 0-112-50.1-112-112s50.1-112 112-112 112 50.1 112 112-50.1 112-112 112z"></path>
                      )}
                    </svg>
                  </button>
                </div>
                {confirmPasswordError && (
                  <div className="h-4 text-xs italic text-red-500">{confirmPasswordError}</div>
                )}
              </label>

              {formError && (
                <div className="text-center text-xs italic text-red-500">{formError}</div>
              )}

              <button
                type="submit"
                className="btn mt-1 h-10 rounded-md py-2 disabled:cursor-not-allowed disabled:opacity-50"
                disabled={submitting}
              >
                {submitting ? 'Resetting...' : 'Reset Password'}
              </button>
            </form>
          </div>
        </div>
      </motion.div>
    </div>
  );
};

export default ResetPassword;
