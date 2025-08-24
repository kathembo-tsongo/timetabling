import React, { useState, useEffect } from "react";
import { Head, usePage, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Shield, Plus, Edit, Trash2, X, Check, CheckCircle, Users, Key, Lock, Unlock, AlertTriangle, Settings, Search, Filter, Tag } from 'lucide-react';

interface Role {
  id: number;
  name: string;
  permissions: { id: number; name: string }[];
}

interface Permission {
  id: number;
  name: string;
 
}

const RolesIndex = () => {
  const { roles: initialRoles = [], permissions: allPermissions = [] } = usePage().props as {
    roles: Role[];
    permissions: Permission[];
  };

  const [roles, setRoles] = useState<Role[]>(initialRoles);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalType, setModalType] = useState<"create" | "edit" | "delete" | "permission" | "">("");
  const [selectedRole, setSelectedRole] = useState<Role | null>(null);
  const [formState, setFormState] = useState<{ 
    name: string; 
   
    permissions: number[] 
  }>({
    name: "",
   
    permissions: [],
  });
  const [permissionForm, setPermissionForm] = useState<{
    name: string;
  
  }>({
    name: "",
    
  });
  const [errors, setErrors] = useState<{ [key: string]: string }>({});
  const [isLoading, setIsLoading] = useState(false);
  const [successMessage, setSuccessMessage] = useState("");
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedCategory, setSelectedCategory] = useState<string>("all");

  // Group permissions by category
  const groupedPermissions = React.useMemo(() => {
    const grouped: { [key: string]: Permission[] } = {};
    
    allPermissions.forEach(permission => {
      const parts = permission.name.split('-');
      const category = parts[0].charAt(0).toUpperCase() + parts[0].slice(1);
      
      if (!grouped[category]) {
        grouped[category] = [];
      }
      grouped[category].push(permission);
    });
    
    return grouped;
  }, [allPermissions]);

  const categories = Object.keys(groupedPermissions);

  // Filter permissions based on search and category
  const filteredPermissions = React.useMemo(() => {
    let filtered = allPermissions;
    
    if (searchTerm) {
      filtered = filtered.filter(permission => 
        permission.name.toLowerCase().includes(searchTerm.toLowerCase())
      );
    }
    
    if (selectedCategory !== "all") {
      filtered = filtered.filter(permission => 
        permission.name.startsWith(selectedCategory.toLowerCase())
      );
    }
    
    return filtered;
  }, [allPermissions, searchTerm, selectedCategory]);

  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => {
        setSuccessMessage("");
      }, 3000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  useEffect(() => {
    if (modalType === "edit" && selectedRole) {
      setFormState({
        name: selectedRole.name || "",
       
        permissions: selectedRole.permissions?.map((p) => p.id) || [],
      });
    } else if (modalType === "create") {
      setFormState({ name: "", permissions: [] });
     }
    setErrors({});
  }, [modalType, selectedRole]);

  const handleOpenModal = (type: "create" | "edit" | "delete" | "permission", role: Role | null = null) => {
    setModalType(type);
    setSelectedRole(role);
    setIsModalOpen(true);
    setErrors({});
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setModalType("");
    setSelectedRole(null);
    setFormState({ name: "", permissions: [] });
    setPermissionForm({ name: "",});
    setErrors({});
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    if (modalType === "permission") {
      // Handle permission creation
      router.post("/permissions", permissionForm, {
        onSuccess: () => {
          handleCloseModal();
          setSuccessMessage("Permission created successfully!");
          setIsLoading(false);
          // Refresh the page to get updated permissions
          router.reload();
        },
        onError: (errors) => {
          setErrors(errors);
          setIsLoading(false);
        },
      });
      return;
    }

    const validationErrors: { [key: string]: string } = {};
    if (!formState.name.trim()) {
      validationErrors.name = "The role name is required";
    }

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      setIsLoading(false);
      return;
    }

    const formData = {
      name: formState.name,
      
      permissions: formState.permissions,
    };

    if (modalType === "create") {
      router.post("/roles", formData, {
        onSuccess: () => {
          handleCloseModal();
          setSuccessMessage("Role created successfully!");
          setIsLoading(false);
          router.reload();
        },
        onError: (errors) => {
          setErrors(errors);
          setIsLoading(false);
        },
      });
    } else if (modalType === "edit" && selectedRole) {
      router.put(`/roles/${selectedRole.id}`, formData, {
        onSuccess: () => {
          handleCloseModal();
          setSuccessMessage("Role updated successfully!");
          setIsLoading(false);
          router.reload();
        },
        onError: (errors) => {
          setErrors(errors);
          setIsLoading(false);
        },
      });
    } else if (modalType === "delete" && selectedRole) {
      router.delete(`/roles/${selectedRole.id}`, {
        onSuccess: () => {
          handleCloseModal();
          setSuccessMessage("Role deleted successfully!");
          setIsLoading(false);
          router.reload();
        },
        onError: (errors) => {
          setErrors(errors);
          setIsLoading(false);
        },
      });
    }
  };

  const togglePermission = (permissionId: number) => {
    setFormState((prev) => ({
      ...prev,
      permissions: prev.permissions.includes(permissionId)
        ? prev.permissions.filter((id) => id !== permissionId)
        : [...prev.permissions, permissionId],
    }));
  };

  return (
    <AuthenticatedLayout>
      <Head title="Roles Management" />
      <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
        <div className="max-w-7xl mx-auto p-6">
          {/* Success message toast */}
          {successMessage && (
            <div className="fixed top-6 right-6 bg-gradient-to-r from-green-400 to-green-600 text-white p-4 rounded-2xl shadow-2xl z-50 transform transition-all duration-500 ease-in-out animate-bounce">
              <div className="flex items-center gap-3">
                <CheckCircle className="h-6 w-6" />
                <p className="font-medium">{successMessage}</p>
              </div>
            </div>
          )}

          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl p-8 border border-white/20 shadow-xl">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center">
                    <Shield className="w-8 h-8 text-white" />
                  </div>
                  <div>
                    <h1 className="text-4xl font-bold bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">
                      Roles & Permissions
                    </h1>
                    <p className="text-gray-600 text-lg">
                      Manage user roles and permissions for your system
                    </p>
                  </div>
                </div>
                <div className="flex gap-3">
                  <button
                    onClick={() => handleOpenModal("permission")}
                    className="group flex items-center gap-2 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                  >
                    <Key className="w-5 h-5 group-hover:rotate-12 transition-transform duration-300" />
                    Add Permission
                  </button>
                  <button
                    onClick={() => handleOpenModal("create")}
                    className="group flex items-center gap-2 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                  >
                    <Plus className="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
                    Create Role
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Roles Content */}
          {roles.length > 0 ? (
            <div className="grid gap-6">
              {roles.map((role, index) => (
                role && (
                  <div
                    key={role.id}
                    className="group bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/20 shadow-lg hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2"
                  >
                    <div className="flex flex-col lg:flex-row gap-6">
                      {/* Role Info Section */}
                      <div className="flex-1">
                        <div className="flex items-start gap-4">
                          <div className="w-12 h-12 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0">
                            <span className="text-white font-bold text-lg">#{index + 1}</span>
                          </div>
                          <div className="flex-1">
                            <div className="flex items-center gap-3 mb-3">
                              <h3 className="text-2xl font-bold text-gray-800">{role.name || "N/A"}</h3>
                              <span className="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded-full font-medium">
                                Role
                              </span>
                            </div>
                            
                            <div className="mb-4">
                              <div className="flex items-center gap-2 mb-3">
                                <Key className="w-4 h-4 text-indigo-500" />
                                <span className="text-sm font-medium text-gray-600">
                                  Permissions ({role.permissions?.length || 0})
                                </span>
                              </div>
                              <div className="flex flex-wrap gap-2">
                                {role.permissions?.map((permission) => (
                                  permission && (
                                    <span
                                      key={permission.id}
                                      className="inline-flex items-center gap-1 bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-700 text-xs font-medium px-3 py-1 rounded-full border border-blue-200"
                                    >
                                      <Lock className="w-3 h-3" />
                                      {permission.name || "N/A"}
                                    </span>
                                  )
                                )) || (
                                  <span className="text-gray-500 text-sm italic">No permissions assigned</span>
                                )}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      {/* Actions Section */}
                      <div className="flex lg:flex-col gap-3 lg:w-40">
                        <button
                          onClick={() => handleOpenModal("edit", role)}
                          className="group/btn flex items-center justify-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white px-4 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg flex-1 lg:flex-none"
                        >
                          <Edit className="w-4 h-4 group-hover/btn:rotate-12 transition-transform duration-300" />
                          <span>Edit</span>
                        </button>
                        <button
                          onClick={() => handleOpenModal("delete", role)}
                          className="group/btn flex items-center justify-center gap-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-4 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg flex-1 lg:flex-none"
                        >
                          <Trash2 className="w-4 h-4 group-hover/btn:scale-110 transition-transform duration-300" />
                          <span>Delete</span>
                        </button>
                      </div>
                    </div>
                  </div>
                )
              ))}
            </div>
          ) : (
            <div className="text-center py-16">
              <div className="bg-white/80 backdrop-blur-sm rounded-3xl p-12 border border-white/20 shadow-lg max-w-2xl mx-auto">
                <div className="w-24 h-24 bg-gradient-to-r from-purple-400 to-indigo-500 rounded-full flex items-center justify-center mx-auto mb-6">
                  <Users className="w-12 h-12 text-white" />
                </div>
                <h3 className="text-2xl font-bold text-gray-800 mb-4">No Roles Found</h3>
                <p className="text-gray-600 text-lg mb-6">
                  Get started by creating your first role to manage user permissions.
                </p>
                <button
                  onClick={() => handleOpenModal("create")}
                  className="inline-flex items-center gap-2 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                >
                  <Plus className="w-5 h-5" />
                  Create First Role
                </button>
              </div>
            </div>
          )}

          {/* Enhanced Modal */}
          {isModalOpen && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-100">
                <div className="p-8">
                  <div className="flex justify-between items-center mb-6">
                    <div className="flex items-center gap-3">
                      <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${
                        modalType === "create" ? "bg-gradient-to-r from-green-400 to-green-600" :
                        modalType === "edit" ? "bg-gradient-to-r from-amber-400 to-orange-500" :
                        modalType === "permission" ? "bg-gradient-to-r from-emerald-400 to-emerald-600" :
                        "bg-gradient-to-r from-red-400 to-red-600"
                      }`}>
                        {modalType === "create" && <Plus className="w-5 h-5 text-white" />}
                        {modalType === "edit" && <Edit className="w-5 h-5 text-white" />}
                        {modalType === "permission" && <Key className="w-5 h-5 text-white" />}
                        {modalType === "delete" && <Trash2 className="w-5 h-5 text-white" />}
                      </div>
                      <h2 className="text-2xl font-bold text-gray-800">
                        {modalType === "create" && "Create New Role"}
                        {modalType === "edit" && "Edit Role"}
                        {modalType === "permission" && "Create New Permission"}
                        {modalType === "delete" && "Delete Role"}
                      </h2>
                    </div>
                    <button
                      onClick={handleCloseModal}
                      className="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200"
                    >
                      <X className="w-5 h-5 text-gray-500" />
                    </button>
                  </div>

                  {modalType === "permission" ? (
                    <form onSubmit={handleSubmit} className="space-y-6">
                      <div>
                        <label htmlFor="permission-name" className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                          <Key className="w-4 h-4 text-emerald-500" />
                          Permission Name
                        </label>
                        <input
                          id="permission-name"
                          type="text"
                          value={permissionForm.name}
                          onChange={(e) => setPermissionForm({ ...permissionForm, name: e.target.value })}
                          required
                          className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-300"
                          placeholder="e.g., manage-students, view-reports"
                          disabled={isLoading}
                        />
                        <p className="text-xs text-gray-500 mt-1">Use kebab-case format (e.g., manage-users, view-dashboard)</p>
                      </div>

                      

                      <div className="flex justify-end gap-3 pt-4 border-t">
                        <button
                          type="button"
                          onClick={handleCloseModal}
                          className="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-medium transition-colors duration-200"
                          disabled={isLoading}
                        >
                          Cancel
                        </button>
                        <button
                          type="submit"
                          className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-xl font-medium transition-all duration-200 disabled:opacity-50"
                          disabled={isLoading}
                        >
                          {isLoading ? (
                            <>
                              <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                              Creating...
                            </>
                          ) : (
                            <>
                              <Key className="w-4 h-4" />
                              Create Permission
                            </>
                          )}
                        </button>
                      </div>
                    </form>
                  ) : modalType === "delete" ? (
                    <div className="space-y-6">
                      <div className="p-6 bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-2xl">
                        <div className="flex items-start gap-4">
                          <AlertTriangle className="w-6 h-6 text-red-500 flex-shrink-0 mt-1" />
                          <div>
                            <h3 className="font-semibold text-red-800 mb-2">Confirm Deletion</h3>
                            <p className="text-red-700">
                              Are you sure you want to delete the role <strong>{selectedRole?.name || "N/A"}</strong>?
                              This action cannot be undone and will remove all associated permissions.
                            </p>
                          </div>
                        </div>
                      </div>

                      <div className="flex justify-end gap-3">
                        <button
                          onClick={handleCloseModal}
                          className="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-medium transition-colors duration-200"
                          disabled={isLoading}
                        >
                          Cancel
                        </button>
                        <button
                          onClick={handleSubmit}
                          className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-xl font-medium transition-all duration-200 disabled:opacity-50"
                          disabled={isLoading}
                        >
                          {isLoading ? (
                            <>
                              <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                              Deleting...
                            </>
                          ) : (
                            <>
                              <Trash2 className="w-4 h-4" />
                              Delete Role
                            </>
                          )}
                        </button>
                      </div>
                    </div>
                  ) : (
                    <form onSubmit={handleSubmit} className="space-y-6">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                          <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                            <Settings className="w-4 h-4 text-indigo-500" />
                            Role Name
                          </label>
                          <input
                            id="name"
                            type="text"
                            value={formState.name}
                            onChange={(e) => setFormState({ ...formState, name: e.target.value })}
                            required
                            className={`w-full border rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all duration-300 ${
                              errors.name ? "border-red-500 bg-red-50" : "border-gray-300"
                            }`}
                            placeholder="Enter role name"
                            disabled={isLoading}
                          />
                          {errors.name && (
                            <p className="text-red-500 text-sm mt-2 flex items-center gap-2">
                              <AlertTriangle className="w-4 h-4" />
                              {errors.name}
                            </p>
                          )}
                        </div>

                        
                      </div>

                      <div>
                        <div className="flex justify-between items-center mb-3">
                          <label className="block text-sm font-medium text-gray-700 flex items-center gap-2">
                            <Key className="w-4 h-4 text-green-500" />
                            Permissions
                          </label>
                          {formState.permissions.length > 0 && (
                            <span className="text-xs bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-800 px-3 py-1 rounded-full font-medium">
                              {formState.permissions.length} selected
                            </span>
                          )}
                        </div>

                        {/* Search and Filter */}
                        <div className="flex gap-3 mb-4">
                          <div className="flex-1 relative">
                            <Search className="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                            <input
                              type="text"
                              placeholder="Search permissions..."
                              value={searchTerm}
                              onChange={(e) => setSearchTerm(e.target.value)}
                              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                          </div>
                          <select
                            value={selectedCategory}
                            onChange={(e) => setSelectedCategory(e.target.value)}
                            className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                          >
                            <option value="all">All Categories</option>
                            {categories.map(category => (
                              <option key={category} value={category.toLowerCase()}>
                                {category}
                              </option>
                            ))}
                          </select>
                        </div>

                        <div className="flex gap-3 mb-4">
                          <button
                            type="button"
                            onClick={() => {
                              const allPermIds = filteredPermissions.map(p => p.id);
                              setFormState(prev => ({
                                ...prev,
                                permissions: [...new Set([...prev.permissions, ...allPermIds])]
                              }));
                            }}
                            className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-green-100 to-green-200 hover:from-green-200 hover:to-green-300 text-green-700 text-sm rounded-xl font-medium transition-all duration-200"
                            disabled={isLoading}
                          >
                            <Check className="w-3 h-3" />
                            Select All Filtered
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              setFormState(prev => ({
                                ...prev,
                                permissions: []
                              }));
                            }}
                            className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-red-100 to-red-200 hover:from-red-200 hover:to-red-300 text-red-700 text-sm rounded-xl font-medium transition-all duration-200"
                            disabled={isLoading}
                          >
                            <X className="w-3 h-3" />
                            Clear All
                          </button>
                        </div>

                        <div className="h-80 overflow-y-auto border rounded-xl p-4 bg-gradient-to-br from-gray-50 to-gray-100">
                          {selectedCategory === "all" ? (
                            // Group by category
                            Object.entries(groupedPermissions).map(([category, permissions]) => (
                              <div key={category} className="mb-6">
                                <div className="flex items-center gap-2 mb-3">
                                  <Tag className="w-4 h-4 text-indigo-500" />
                                  <h4 className="font-semibold text-gray-700">{category}</h4>
                                  <span className="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full">
                                    {permissions.length}
                                  </span>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 ml-6">
                                  {permissions
                                    .filter(permission => 
                                      !searchTerm || permission.name.toLowerCase().includes(searchTerm.toLowerCase())
                                    )
                                    .map((permission) => (
                                      <div
                                        key={permission.id}
                                        className={`flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all duration-200 ${
                                          formState.permissions.includes(permission.id)
                                            ? 'bg-gradient-to-r from-blue-100 to-indigo-100 border border-blue-200 shadow-sm'
                                            : 'bg-white hover:bg-gray-50 border border-gray-200'
                                        }`}
                                        onClick={() => togglePermission(permission.id)}
                                      >
                                        <input
                                          type="checkbox"
                                          checked={formState.permissions.includes(permission.id)}
                                          onChange={() => togglePermission(permission.id)}
                                          className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                                          disabled={isLoading}
                                        />
                                        <div className="flex items-center gap-2">
                                          {formState.permissions.includes(permission.id) ? (
                                            <Unlock className="w-4 h-4 text-blue-600" />
                                          ) : (
                                            <Lock className="w-4 h-4 text-gray-400" />
                                          )}
                                          <div>
                                            <label className={`text-sm cursor-pointer select-none ${
                                              formState.permissions.includes(permission.id)
                                                ? 'font-medium text-blue-700'
                                                : 'text-gray-700'
                                            }`}>
                                              {permission.name}
                                            </label>
                                            
                                          </div>
                                        </div>
                                      </div>
                                    ))}
                                </div>
                              </div>
                            ))
                          ) : (
                            // Show filtered permissions
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                              {filteredPermissions.map((permission) => (
                                <div
                                  key={permission.id}
                                  className={`flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all duration-200 ${
                                    formState.permissions.includes(permission.id)
                                      ? 'bg-gradient-to-r from-blue-100 to-indigo-100 border border-blue-200 shadow-sm'
                                      : 'bg-white hover:bg-gray-50 border border-gray-200'
                                  }`}
                                  onClick={() => togglePermission(permission.id)}
                                >
                                  <input
                                    type="checkbox"
                                    checked={formState.permissions.includes(permission.id)}
                                    onChange={() => togglePermission(permission.id)}
                                    className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                                    disabled={isLoading}
                                  />
                                  <div className="flex items-center gap-2">
                                    {formState.permissions.includes(permission.id) ? (
                                      <Unlock className="w-4 h-4 text-blue-600" />
                                    ) : (
                                      <Lock className="w-4 h-4 text-gray-400" />
                                    )}
                                    <div>
                                      <label className={`text-sm cursor-pointer select-none ${
                                        formState.permissions.includes(permission.id)
                                          ? 'font-medium text-blue-700'
                                          : 'text-gray-700'
                                      }`}>
                                        {permission.name}
                                      </label>
                                      
                                    </div>
                                  </div>
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      </div>

                      <div className="flex justify-end gap-3 pt-4 border-t">
                        <button
                          type="button"
                          onClick={handleCloseModal}
                          className="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-medium transition-colors duration-200"
                          disabled={isLoading}
                        >
                          Cancel
                        </button>
                        <button
                          type="submit"
                          className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-xl font-medium transition-all duration-200 disabled:opacity-50"
                          disabled={isLoading}
                        >
                          {isLoading ? (
                            <>
                              <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                              {modalType === "create" ? "Creating..." : "Updating..."}
                            </>
                          ) : (
                            <>
                              {modalType === "create" ? (
                                <>
                                  <Plus className="w-4 h-4" />
                                  Create Role
                                </>
                              ) : (
                                <>
                                  <Edit className="w-4 h-4" />
                                  Update Role
                                </>
                              )}
                            </>
                          )}
                        </button>
                      </div>
                    </form>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
};

export default RolesIndex;