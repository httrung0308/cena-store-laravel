import { useState, useEffect } from 'react';
import { useRouter } from 'next/router';
import { userAdminService } from '@/common/api/services/userAdminService';
import {
  UserAdminResponse,
  RoleResponse,
  UserRolesResponse,
} from '@/common/types/backend';
import { AiOutlineArrowLeft, AiOutlinePlus, AiOutlineClose } from 'react-icons/ai';
import { BiLoaderAlt } from 'react-icons/bi';
import { useModal } from '@/common/recoil/modal';
import { useToast } from '@/common/recoil/toast/toast.hooks';

const AdminUserDetail = () => {
  const router = useRouter();
  const { id } = router.query;
  const { openModal, closeModal } = useModal();
  const toast = useToast();

  const [user, setUser] = useState<UserAdminResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [fullName, setFullName] = useState('');
  const [phone, setPhone] = useState('');
  const [isActive, setIsActive] = useState(false);
  const [saving, setSaving] = useState(false);

  const ROLES: RoleResponse[] = [
    { roleId: 1, roleName: 'Admin' },
    { roleId: 2, roleName: 'Customer' },
    { roleId: 3, roleName: 'Staff' },
  ];

  const fetchUserData = async () => {
    if (!id) return;

    try {
      setLoading(true);
      setError(null);

      const userData = await userAdminService.getUserById(Number(id));

      setUser(userData);
      setFullName(userData.fullName);
      setPhone(userData.phone);
      setIsActive(userData.isActive);
    } catch (err: any) {
      setError(err.message || 'Failed to load user details');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUserData();
  }, [id]);

  const handleUpdateUser = async () => {
    if (!user) return;
    try {
      setSaving(true);
      await userAdminService.updateUser(user.userId, {
        fullName,
        phone,
        isActive,
      });
      // Manuall update local state since API might return null/void on success
      setUser({ ...user, fullName, phone, isActive });
      toast.success('User updated successfully');
    } catch (err: any) {
      toast.error(err.message || 'Failed to update user');
    } finally {
      setSaving(false);
    }
  };

  const handleAssignRole = (role: RoleResponse) => {
    openModal(
      <div className="rounded-3xl bg-white p-8">
        <h2 className="text-2xl font-bold">Assign Role</h2>
        <p className="mt-4 text-zinc-600">
          Are you sure you want to assign the role "{role.roleName}" to {user?.fullName}?
        </p>
        <div className="mt-6 flex gap-4">
          <button
            onClick={closeModal}
            className="flex-1 rounded-full border border-zinc-200 px-6 py-3 font-semibold transition hover:border-zinc-900"
          >
            Cancel
          </button>
          <button
            onClick={async () => {
              try {
                await userAdminService.assignRole(Number(id), role.roleId);
                if (user) {
                  setUser({ ...user, roles: [...user.roles, role.roleName] });
                }
                closeModal();
                toast.success(`Role "${role.roleName}" assigned successfully`);
              } catch (err: any) {
                toast.error(err.message || 'Failed to assign role');
              }
            }}
            className="flex-1 rounded-full bg-green-600 px-6 py-3 font-semibold text-white transition hover:scale-105"
          >
            Assign
          </button>
        </div>
      </div>
    );
  };

  const handleRevokeRole = (roleName: string) => {
    const roleToRemove = ROLES.find(r => r.roleName === roleName);
    if (!roleToRemove) {
      toast.error(`Cannot identify role ID for ${roleName}`);
      return;
    }

    openModal(
      <div className="rounded-3xl bg-white p-8">
        <h2 className="text-2xl font-bold">Revoke Role</h2>
        <p className="mt-4 text-zinc-600">
          Are you sure you want to revoke the role "{roleName}" from {user?.fullName}?
        </p>
        <div className="mt-6 flex gap-4">
          <button
            onClick={closeModal}
            className="flex-1 rounded-full border border-zinc-200 px-6 py-3 font-semibold transition hover:border-zinc-900"
          >
            Cancel
          </button>
          <button
            onClick={async () => {
              try {
                await userAdminService.revokeRole(Number(id), roleToRemove.roleId);
                if (user) {
                  setUser({ ...user, roles: user.roles.filter(r => r !== roleName) });
                }
                closeModal();
                toast.success(`Role "${roleName}" revoked successfully`);
              } catch (err: any) {
                toast.error(err.message || 'Failed to revoke role');
              }
            }}
            className="flex-1 rounded-full bg-red-600 px-6 py-3 font-semibold text-white transition hover:scale-105"
          >
            Revoke
          </button>
        </div>
      </div>
    );
  };

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString('en-GB');
  };

  const formatJoinDate = (dateString: string) => {
    if (!dateString) return 'Unknown';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString('en-GB');
  };

  const availableRoles = ROLES.filter(
    (role) => !user?.roles.includes(role.roleName)
  );

  if (loading) {
    return (
      <section className="px-4 py-20 sm:px-8">
        <div className="mx-auto flex max-w-7xl items-center justify-center py-20">
          <BiLoaderAlt className="animate-spin text-zinc-400" size={48} />
        </div>
      </section>
    );
  }

  if (error || !user) {
    return (
      <section className="px-4 py-20 sm:px-8">
        <div className="mx-auto max-w-7xl">
          <div className="rounded-3xl border border-red-200 bg-red-50 p-12 text-center text-red-600">
            <p className="text-lg">{error || 'User not found'}</p>
            <button
              onClick={() => router.push('/admin/users')}
              className="btn mt-4"
            >
              Back to Users
            </button>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="px-4 py-20 sm:px-8">
      <div className="mx-auto flex max-w-7xl flex-col gap-8">
        <div className="space-y-4">
          <button
            onClick={() => router.push('/admin/users')}
            className="flex items-center gap-2 text-zinc-600 transition hover:text-zinc-900"
          >
            <AiOutlineArrowLeft size={20} />
            <span className="font-semibold">Back to Users List</span>
          </button>
          <p className="text-sm uppercase tracking-[0.35em] text-zinc-400">User Details</p>
          <div className="flex items-center justify-between">
            <h1 className="text-5xl font-bold">{user.fullName}</h1>
            <div className="flex items-center gap-4">
            </div>
          </div>
        </div>

        <div className="grid gap-8 lg:grid-cols-3">
          <div className="space-y-6">
            <div className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
              <div className="flex flex-col items-center text-center">
                <img
                  src={`https://ui-avatars.com/api/?name=${encodeURIComponent(user.fullName)}&background=066fd1&color=fff&size=128`}
                  alt={user.fullName}
                  className="h-32 w-32 rounded-full"
                />
                <h2 className="mt-4 text-2xl font-bold text-zinc-900">{user.fullName}</h2>
                <p className="mt-1 text-zinc-600">{user.email}</p>

                <div className="mt-6 flex flex-wrap items-center justify-center gap-4">
                  <div className="flex flex-wrap justify-center gap-2">
                    {user.roles && user.roles.length > 0 ? (
                      user.roles.map((role) => (
                        <span
                          key={role}
                          className={`inline-block rounded-full px-3 py-1 text-sm font-semibold ${role === 'Admin'
                              ? 'bg-red-100 text-red-600'
                              : 'bg-blue-100 text-blue-600'
                            }`}
                        >
                          {role}
                        </span>
                      ))
                    ) : (
                      <span className="text-sm text-zinc-400">No roles</span>
                    )}
                  </div>

                  <span
                    className={`inline-block rounded-full px-4 py-2 text-sm font-semibold ${user.isActive
                        ? 'bg-green-100 text-green-600'
                        : 'bg-red-100 text-red-600'
                      }`}
                  >
                    {user.isActive ? 'Active' : 'Inactive'}
                  </span>
                </div>
              </div>
            </div>

            <div className="rounded-3xl border border-zinc-200 bg-white shadow-sm overflow-hidden">
              <div className="border-b border-zinc-200 bg-zinc-50 px-6 py-4">
                <h3 className="font-semibold text-zinc-900">User Metadata</h3>
              </div>
              <div className="divide-y divide-zinc-100">
                <div className="flex justify-between px-6 py-3">
                  <span className="text-sm font-medium text-zinc-500">User ID</span>
                  <span className="text-sm font-semibold text-zinc-900">{user.userId}</span>
                </div>
                <div className="flex justify-between px-6 py-3">
                  <span className="text-sm font-medium text-zinc-500">Email</span>
                  <span className="text-sm font-semibold text-zinc-900">{user.email}</span>
                </div>
                <div className="flex justify-between px-6 py-3">
                  <span className="text-sm font-medium text-zinc-500">Joined</span>
                  <span className="text-sm font-semibold text-zinc-900">{formatJoinDate(user.createdAt)}</span>
                </div>
                <div className="flex justify-between px-6 py-3">
                  <span className="text-sm font-medium text-zinc-500">Last Login</span>
                  <span className="text-sm font-semibold text-zinc-900">{formatDate(user.lastLogin)}</span>
                </div>
              </div>
            </div>

          </div>

          <div className="lg:col-span-2 space-y-8">
            <div className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
              <h2 className="text-2xl font-bold mb-6">Edit Information</h2>
              <div className="grid gap-6">
                <div className="grid gap-2">
                  <label className="text-sm font-semibold text-zinc-700">Full Name</label>
                  <input
                    type="text"
                    className="input"
                    value={fullName}
                    onChange={(e) => setFullName(e.target.value)}
                  />
                </div>
                <div className="grid gap-2">
                  <label className="text-sm font-semibold text-zinc-700">Phone</label>
                  <input
                    type="text"
                    className="input"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                  />
                </div>
                <div className="flex items-center gap-3">
                  <label className="text-sm font-semibold text-zinc-700">Active Status:</label>
                  <button
                    onClick={() => setIsActive(!isActive)}
                    className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${isActive ? 'bg-green-500' : 'bg-zinc-200'
                      }`}
                  >
                    <span
                      className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${isActive ? 'translate-x-6' : 'translate-x-1'
                        }`}
                    />
                  </button>
                  <span className="text-sm text-zinc-600">{isActive ? 'Active' : 'Inactive'}</span>
                </div>

                <div className="flex justify-end pt-4">
                  <button
                    onClick={handleUpdateUser}
                    disabled={saving}
                    className="btn bg-black text-white hover:bg-zinc-800 disabled:opacity-50"
                  >
                    {saving ? 'Saving...' : 'Save Changes'}
                  </button>
                </div>
              </div>
            </div>

            <div className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
              <div className="mb-6 flex items-center justify-between">
                <h2 className="text-2xl font-bold">Role Management</h2>
              </div>

              <div className="space-y-6">
                <div>
                  <h3 className="mb-3 text-lg font-semibold text-zinc-900">Current Roles</h3>
                  {user && user.roles.length > 0 ? (
                    <div className="space-y-2">
                      {user.roles.map((role) => (
                        <div
                          key={role}
                          className="flex items-center justify-between rounded-xl border border-zinc-200 p-4"
                        >
                          <span className="font-semibold text-zinc-900">{role}</span>
                          <button
                            onClick={() => handleRevokeRole(role)}
                            className="flex items-center gap-2 rounded-full bg-red-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-600"
                          >
                            <AiOutlineClose size={14} />
                            Remove
                          </button>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-zinc-500">No roles assigned</p>
                  )}
                </div>

                <div>
                  <h3 className="mb-3 text-lg font-semibold text-zinc-900">Available Roles</h3>
                  {availableRoles.length > 0 ? (
                    <div className="space-y-2">
                      {availableRoles.map((role) => (
                        <div
                          key={role.roleId}
                          className="flex items-center justify-between rounded-xl border border-zinc-200 p-4"
                        >
                          <span className="font-semibold text-zinc-900">{role.roleName}</span>
                          <button
                            onClick={() => handleAssignRole(role)}
                            className="flex items-center gap-2 rounded-full bg-green-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-600"
                          >
                            <AiOutlinePlus size={14} />
                            Assign
                          </button>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-zinc-500">All roles assigned</p>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default AdminUserDetail;
