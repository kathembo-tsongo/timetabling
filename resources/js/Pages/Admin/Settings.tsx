import React, { useState } from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { 
  Settings as SettingsIcon, 
  User, 
  Bell, 
  Shield, 
  Palette, 
  Globe, 
  Database, 
  Mail, 
  Save, 
  Eye, 
  EyeOff,
  Moon,
  Sun,
  Volume2,
  VolumeX,
  Calendar,
  Clock,
  Languages,
  Smartphone,
  Lock,
  Key,
  RefreshCw,
  Download,
  Upload,
  Trash2,
  AlertCircle,
  CheckCircle
} from 'lucide-react';

const Settings = () => {
  const [activeTab, setActiveTab] = useState('general');
  const [showPassword, setShowPassword] = useState(false);
  const [darkMode, setDarkMode] = useState(false);
  const [notifications, setNotifications] = useState(true);
  const [emailNotifications, setEmailNotifications] = useState(true);
  const [soundEnabled, setSoundEnabled] = useState(true);
  const [autoSave, setAutoSave] = useState(true);
  const [language, setLanguage] = useState('en');
  const [timezone, setTimezone] = useState('UTC');
  const [twoFactor, setTwoFactor] = useState(false);

  const tabs = [
    { id: 'general', label: 'General', icon: SettingsIcon },
    { id: 'profile', label: 'Profile', icon: User },
    { id: 'notifications', label: 'Notifications', icon: Bell },
    { id: 'security', label: 'Security', icon: Shield },
    { id: 'appearance', label: 'Appearance', icon: Palette },
    { id: 'system', label: 'System', icon: Database },
  ];

  const handleSave = () => {
    // Save logic here
    console.log('Settings saved');
  };

  return (
    <AuthenticatedLayout>
      <Head title="Settings" />
      <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
        <div className="max-w-7xl mx-auto p-6">
          {/* Header Section */}
          <div className="mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-3xl p-8 border border-white/20 shadow-xl">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center">
                    <SettingsIcon className="w-8 h-8 text-white" />
                  </div>
                  <div>
                    <h1 className="text-4xl font-bold bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">
                      Settings
                    </h1>
                    <p className="text-gray-600 text-lg">
                      Customize your application preferences and configuration
                    </p>
                  </div>
                </div>
                
                <button
                  onClick={handleSave}
                  className="group flex items-center gap-2 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                >
                  <Save className="w-5 h-5 group-hover:scale-110 transition-transform duration-300" />
                  Save All
                </button>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {/* Sidebar Navigation */}
            <div className="lg:col-span-1">
              <div className="bg-white/80 backdrop-blur-sm rounded-2xl p-6 border border-white/20 shadow-lg sticky top-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-4">Categories</h3>
                <nav className="space-y-2">
                  {tabs.map((tab) => (
                    <button
                      key={tab.id}
                      onClick={() => setActiveTab(tab.id)}
                      className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left transition-all duration-300 ${
                        activeTab === tab.id
                          ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg transform scale-105'
                          : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800'
                      }`}
                    >
                      <tab.icon className="w-5 h-5" />
                      <span className="font-medium">{tab.label}</span>
                    </button>
                  ))}
                </nav>
              </div>
            </div>

            {/* Main Content */}
            <div className="lg:col-span-3">
              <div className="bg-white/80 backdrop-blur-sm rounded-2xl border border-white/20 shadow-lg overflow-hidden">
                
                {/* General Settings */}
                {activeTab === 'general' && (
                  <div className="p-8">
                    <div className="flex items-center gap-3 mb-6">
                      <SettingsIcon className="w-6 h-6 text-indigo-500" />
                      <h2 className="text-2xl font-bold text-gray-800">General Settings</h2>
                    </div>
                    
                    <div className="space-y-6">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">
                            Application Name
                          </label>
                          <input
                            type="text"
                            defaultValue="Timetabling System"
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          />
                        </div>
                        
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                            <Languages className="w-4 h-4 text-green-500" />
                            Language
                          </label>
                          <select
                            value={language}
                            onChange={(e) => setLanguage(e.target.value)}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          >
                            <option value="en">English</option>
                            <option value="es">Spanish</option>
                            <option value="fr">French</option>
                            <option value="de">German</option>
                          </select>
                        </div>
                        
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                            <Clock className="w-4 h-4 text-blue-500" />
                            Timezone
                          </label>
                          <select
                            value={timezone}
                            onChange={(e) => setTimezone(e.target.value)}
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          >
                            <option value="UTC">UTC</option>
                            <option value="EST">Eastern Time</option>
                            <option value="PST">Pacific Time</option>
                            <option value="GMT">Greenwich Mean Time</option>
                          </select>
                        </div>
                        
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                            <Calendar className="w-4 h-4 text-purple-500" />
                            Date Format
                          </label>
                          <select className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option>MM/DD/YYYY</option>
                            <option>DD/MM/YYYY</option>
                            <option>YYYY-MM-DD</option>
                          </select>
                        </div>
                      </div>
                      
                      <div className="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                        <div className="flex items-center gap-3">
                          <Save className="w-5 h-5 text-blue-500" />
                          <div>
                            <h4 className="font-medium text-blue-800">Auto Save</h4>
                            <p className="text-sm text-blue-600">Automatically save changes</p>
                          </div>
                        </div>
                        <button
                          onClick={() => setAutoSave(!autoSave)}
                          className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 ${
                            autoSave ? 'bg-blue-500' : 'bg-gray-300'
                          }`}
                        >
                          <span
                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-300 ${
                              autoSave ? 'translate-x-6' : 'translate-x-1'
                            }`}
                          />
                        </button>
                      </div>
                    </div>
                  </div>
                )}

                {/* Profile Settings */}
                {activeTab === 'profile' && (
                  <div className="p-8">
                    <div className="flex items-center gap-3 mb-6">
                      <User className="w-6 h-6 text-indigo-500" />
                      <h2 className="text-2xl font-bold text-gray-800">Profile Settings</h2>
                    </div>
                    
                    <div className="space-y-6">
                      <div className="flex items-center gap-6">
                        <div className="w-24 h-24 bg-gradient-to-r from-purple-400 to-pink-400 rounded-full flex items-center justify-center">
                          <User className="w-12 h-12 text-white" />
                        </div>
                        <div className="space-y-2">
                          <button className="flex items-center gap-2 px-4 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors">
                            <Upload className="w-4 h-4" />
                            Upload Photo
                          </button>
                          <button className="flex items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                            <Trash2 className="w-4 h-4" />
                            Remove
                          </button>
                        </div>
                      </div>
                      
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                          <input
                            type="text"
                            defaultValue="John"
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          />
                        </div>
                        
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                          <input
                            type="text"
                            defaultValue="Doe"
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          />
                        </div>
                        
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                            <Mail className="w-4 h-4 text-red-500" />
                            Email Address
                          </label>
                          <input
                            type="email"
                            defaultValue="john.doe@example.com"
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          />
                        </div>
                        
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                            <Smartphone className="w-4 h-4 text-green-500" />
                            Phone Number
                          </label>
                          <input
                            type="tel"
                            defaultValue="+1 (555) 123-4567"
                            className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                          />
                        </div>
                      </div>
                      
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                        <textarea
                          rows={4}
                          defaultValue="Lorem ipsum dolor sit amet, consectetur adipiscing elit."
                          className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                      </div>
                    </div>
                  </div>
                )}

                {/* Notifications Settings */}
                {activeTab === 'notifications' && (
                  <div className="p-8">
                    <div className="flex items-center gap-3 mb-6">
                      <Bell className="w-6 h-6 text-indigo-500" />
                      <h2 className="text-2xl font-bold text-gray-800">Notification Settings</h2>
                    </div>
                    
                    <div className="space-y-6">
                      <div className="space-y-4">
                        <div className="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-green-200">
                          <div className="flex items-center gap-3">
                            <Bell className="w-5 h-5 text-green-500" />
                            <div>
                              <h4 className="font-medium text-green-800">Push Notifications</h4>
                              <p className="text-sm text-green-600">Receive push notifications in your browser</p>
                            </div>
                          </div>
                          <button
                            onClick={() => setNotifications(!notifications)}
                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 ${
                              notifications ? 'bg-green-500' : 'bg-gray-300'
                            }`}
                          >
                            <span
                              className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-300 ${
                                notifications ? 'translate-x-6' : 'translate-x-1'
                              }`}
                            />
                          </button>
                        </div>
                        
                        <div className="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                          <div className="flex items-center gap-3">
                            <Mail className="w-5 h-5 text-blue-500" />
                            <div>
                              <h4 className="font-medium text-blue-800">Email Notifications</h4>
                              <p className="text-sm text-blue-600">Receive notifications via email</p>
                            </div>
                          </div>
                          <button
                            onClick={() => setEmailNotifications(!emailNotifications)}
                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 ${
                              emailNotifications ? 'bg-blue-500' : 'bg-gray-300'
                            }`}
                          >
                            <span
                              className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-300 ${
                                emailNotifications ? 'translate-x-6' : 'translate-x-1'
                              }`}
                            />
                          </button>
                        </div>
                        
                        <div className="flex items-center justify-between p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl border border-purple-200">
                          <div className="flex items-center gap-3">
                            {soundEnabled ? <Volume2 className="w-5 h-5 text-purple-500" /> : <VolumeX className="w-5 h-5 text-purple-500" />}
                            <div>
                              <h4 className="font-medium text-purple-800">Sound Notifications</h4>
                              <p className="text-sm text-purple-600">Play sound for notifications</p>
                            </div>
                          </div>
                          <button
                            onClick={() => setSoundEnabled(!soundEnabled)}
                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 ${
                              soundEnabled ? 'bg-purple-500' : 'bg-gray-300'
                            }`}
                          >
                            <span
                              className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-300 ${
                                soundEnabled ? 'translate-x-6' : 'translate-x-1'
                              }`}
                            />
                          </button>
                        </div>
                      </div>
                      
                      <div>
                        <h3 className="text-lg font-semibold text-gray-800 mb-4">Notification Types</h3>
                        <div className="space-y-3">
                          {[
                            'New timetable updates',
                            'Schedule conflicts',
                            'System maintenance',
                            'Account security alerts',
                            'Weekly reports'
                          ].map((type, index) => (
                            <div key={index} className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                              <input
                                type="checkbox"
                                defaultChecked={index < 3}
                                className="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 focus:ring-2"
                              />
                              <span className="text-gray-700">{type}</span>
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* Security Settings */}
                {activeTab === 'security' && (
                  <div className="p-8">
                    <div className="flex items-center gap-3 mb-6">
                      <Shield className="w-6 h-6 text-indigo-500" />
                      <h2 className="text-2xl font-bold text-gray-800">Security Settings</h2>
                    </div>
                    
                    <div className="space-y-6">
                      <div className="p-6 bg-gradient-to-r from-red-50 to-pink-50 rounded-xl border border-red-200">
                        <h3 className="text-lg font-semibold text-red-800 mb-4 flex items-center gap-2">
                          <Lock className="w-5 h-5" />
                          Change Password
                        </h3>
                        <div className="space-y-4">
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <div className="relative">
                              <input
                                type={showPassword ? "text" : "password"}
                                className="w-full px-4 py-3 pr-12 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                              />
                              <button
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                              >
                                {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                              </button>
                            </div>
                          </div>
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                              <input
                                type="password"
                                className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                              />
                            </div>
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                              <input
                                type="password"
                                className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                              />
                            </div>
                          </div>
                          <button className="flex items-center gap-2 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                            <Key className="w-4 h-4" />
                            Update Password
                          </button>
                        </div>
                      </div>
                      
                      <div className="flex items-center justify-between p-4 bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl border border-amber-200">
                        <div className="flex items-center gap-3">
                          <Shield className="w-5 h-5 text-amber-500" />
                          <div>
                            <h4 className="font-medium text-amber-800">Two-Factor Authentication</h4>
                            <p className="text-sm text-amber-600">Add an extra layer of security to your account</p>
                          </div>
                        </div>
                        <button
                          onClick={() => setTwoFactor(!twoFactor)}
                          className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 ${
                            twoFactor ? 'bg-amber-500' : 'bg-gray-300'
                          }`}
                        >
                          <span
                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-300 ${
                              twoFactor ? 'translate-x-6' : 'translate-x-1'
                            }`}
                          />
                        </button>
                      </div>
                      
                      <div className="space-y-4">
                        <h3 className="text-lg font-semibold text-gray-800">Active Sessions</h3>
                        <div className="space-y-3">
                          {[
                            { device: 'Chrome on Windows', location: 'New York, USA', active: true },
                            { device: 'Safari on iPhone', location: 'San Francisco, USA', active: false },
                            { device: 'Firefox on Mac', location: 'London, UK', active: false }
                          ].map((session, index) => (
                            <div key={index} className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                              <div className="flex items-center gap-3">
                                <div className={`w-3 h-3 rounded-full ${session.active ? 'bg-green-500' : 'bg-gray-300'}`}></div>
                                <div>
                                  <p className="font-medium text-gray-800">{session.device}</p>
                                  <p className="text-sm text-gray-600">{session.location}</p>
                                </div>
                              </div>
                              {!session.active && (
                                <button className="text-red-600 hover:text-red-800 text-sm">Revoke</button>
                              )}
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* Appearance Settings */}
                {activeTab === 'appearance' && (
                  <div className="p-8">
                    <div className="flex items-center gap-3 mb-6">
                      <Palette className="w-6 h-6 text-indigo-500" />
                      <h2 className="text-2xl font-bold text-gray-800">Appearance Settings</h2>
                    </div>
                    
                    <div className="space-y-6">
                      <div className="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                        <div className="flex items-center gap-3">
                          {darkMode ? <Moon className="w-5 h-5 text-indigo-500" /> : <Sun className="w-5 h-5 text-yellow-500" />}
                          <div>
                            <h4 className="font-medium text-gray-800">Dark Mode</h4>
                            <p className="text-sm text-gray-600">Switch between light and dark themes</p>
                          </div>
                        </div>
                        <button
                          onClick={() => setDarkMode(!darkMode)}
                          className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 ${
                            darkMode ? 'bg-indigo-500' : 'bg-gray-300'
                          }`}
                        >
                          <span
                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-300 ${
                              darkMode ? 'translate-x-6' : 'translate-x-1'
                            }`}
                          />
                        </button>
                      </div>
                      
                      <div>
                        <h3 className="text-lg font-semibold text-gray-800 mb-4">Theme Colors</h3>
                        <div className="grid grid-cols-4 gap-4">
                          {[
                            'bg-blue-500',
                            'bg-purple-500',
                            'bg-green-500',
                            'bg-red-500',
                            'bg-yellow-500',
                            'bg-pink-500',
                            'bg-indigo-500',
                            'bg-gray-500'
                          ].map((color, index) => (
                            <button
                              key={index}
                              className={`w-16 h-16 ${color} rounded-xl border-4 border-transparent hover:border-white hover:shadow-lg transition-all duration-300 ${
                                index === 0 ? 'border-white shadow-lg' : ''
                              }`}
                            />
                          ))}
                        </div>
                      </div>
                      
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Font Size</label>
                        <select className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                          <option>Small</option>
                          <option>Medium</option>
                          <option>Large</option>
                          <option>Extra Large</option>
                        </select>
                      </div>
                    </div>
                  </div>
                )}

                {/* System Settings */}
                {activeTab === 'system' && (
                  <div className="p-8">
                    <div className="flex items-center gap-3 mb-6">
                      <Database className="w-6 h-6 text-indigo-500" />
                      <h2 className="text-2xl font-bold text-gray-800">System Settings</h2>
                    </div>
                    
                    <div className="space-y-6">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                          <div className="flex items-center gap-3 mb-4">
                            <Download className="w-6 h-6 text-blue-500" />
                            <h3 className="text-lg font-semibold text-blue-800">Export Data</h3>
                          </div>
                          <p className="text-sm text-blue-600 mb-4">Download all your data in various formats</p>
                          <div className="space-y-2">
                            <button className="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                              Export as CSV
                            </button>
                            <button className="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                              Export as JSON
                            </button>
                            <button className="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                              Export as PDF
                            </button>
                          </div>
                        </div>
                        
                        <div className="p-6 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-green-200">
                          <div className="flex items-center gap-3 mb-4">
                            <Upload className="w-6 h-6 text-green-500" />
                            <h3 className="text-lg font-semibold text-green-800">Import Data</h3>
                          </div>
                          <p className="text-sm text-green-600 mb-4">Upload and restore your data from backup files</p>
                          <div className="space-y-2">
                            <button className="w-full px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                              Import CSV
                            </button>
                            <button className="w-full px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                              Import JSON
                            </button>
                            <button className="w-full px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                              Restore Backup
                            </button>
                          </div>
                        </div>
                      </div>
                      
                      <div className="p-6 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl border border-purple-200">
                        <div className="flex items-center gap-3 mb-4">
                          <RefreshCw className="w-6 h-6 text-purple-500" />
                          <h3 className="text-lg font-semibold text-purple-800">System Maintenance</h3>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                          <button className="flex items-center gap-2 px-4 py-3 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
                            <RefreshCw className="w-4 h-4" />
                            Clear Cache
                          </button>
                          <button className="flex items-center gap-2 px-4 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                            <Database className="w-4 h-4" />
                            Optimize DB
                          </button>
                          <button className="flex items-center gap-2 px-4 py-3 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors">
                            <CheckCircle className="w-4 h-4" />
                            Run Diagnostics
                          </button>
                          <button className="flex items-center gap-2 px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                            <Download className="w-4 h-4" />
                            Backup System
                          </button>
                        </div>
                      </div>
                      
                      <div className="p-6 bg-gradient-to-r from-red-50 to-pink-50 rounded-xl border border-red-200">
                        <div className="flex items-center gap-3 mb-4">
                          <AlertTriangle className="w-6 h-6 text-red-500" />
                          <h3 className="text-lg font-semibold text-red-800">Danger Zone</h3>
                        </div>
                        <p className="text-sm text-red-600 mb-4">
                          These actions are irreversible. Please proceed with caution.
                        </p>
                        <div className="space-y-3">
                          <button className="flex items-center gap-2 px-4 py-3 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors">
                            <RefreshCw className="w-4 h-4" />
                            Reset All Settings
                          </button>
                          <button className="flex items-center gap-2 px-4 py-3 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors">
                            <Trash2 className="w-4 h-4" />
                            Delete All Data
                          </button>
                          <button className="flex items-center gap-2 px-4 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                            <X className="w-4 h-4" />
                            Delete Account
                          </button>
                        </div>
                      </div>
                      
                      <div className="p-6 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                        <h3 className="text-lg font-semibold text-gray-800 mb-4">System Information</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                          <div className="space-y-2">
                            <div className="flex justify-between">
                              <span className="text-gray-600">Version:</span>
                              <span className="font-medium">v2.1.0</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-600">Last Updated:</span>
                              <span className="font-medium">Dec 15, 2024</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-600">Database Size:</span>
                              <span className="font-medium">1.2 GB</span>
                            </div>
                          </div>
                          <div className="space-y-2">
                            <div className="flex justify-between">
                              <span className="text-gray-600">Active Users:</span>
                              <span className="font-medium">247</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-600">Server Uptime:</span>
                              <span className="font-medium">99.9%</span>
                            </div>
                            <div className="flex justify-between">
                              <span className="text-gray-600">Next Backup:</span>
                              <span className="font-medium">Tonight 2:00 AM</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
};

export default Settings;