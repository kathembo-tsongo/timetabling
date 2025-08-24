"use client"

import type React from "react"
import { useState } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface School {
  id: number
  name: string
}

interface Program {
  id: number
  name: string
  code: string
  school_id: number | null
}

interface PaginationLinks {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedPrograms {
  data: Program[]
  links: PaginationLinks[]
  total: number
  per_page: number
  current_page: number
}

// Program-specific pages with enhanced functionality
const programPages = [
  {
    id: "classrooms",
    name: "Classrooms",
    icon: "M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4",
    description: "Manage classrooms for",
    color: "from-purple-500 to-indigo-500",
  },
  {
    id: "class-timetables",
    name: "Class Timetables",
    icon: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
    description: "Manage class timetables for",
    color: "from-purple-500 to-indigo-500",
  },
  {
    id: "exam-rooms",
    name: "Exam Rooms",
    icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
    description: "Manage exam rooms for",
    color: "from-purple-500 to-indigo-500",
  },
  {
    id: "exam-time-slots",
    name: "Exam Time Slots",
    icon: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
    description: "Manage exam time slots for",
    color: "from-purple-500 to-indigo-500",
  },
  {
    id: "exam-timetable",
    name: "Exam Timetable",
    icon: "M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 0V6a2 2 0 012-2h4a2 2 0 012 2v1m-6 0h8m-9 2v8a2 2 0 002 2h8a2 2 0 002-2v-8M5 9h14l-1 12H6L5 9z",
    description: "Manage exam timetable for",
    color: "from-purple-500 to-indigo-500",
  },
  {
    id: "manage-enrollments",
    name: "Manage Enrollments",
    icon: "M12 4.354a4 4 0 110 6.292 4 4 0 010-6.292zM15 8a3 3 0 11-6 0 3 3 0 016 0z",
    description: "Manage enrollments for",
    color: "from-purple-500 to-indigo-500",
  },
]

const Programs = () => {
  const { programs, perPage, search, schools } = usePage().props as {
    programs: PaginatedPrograms
    perPage: number
    search: string
    schools: School[]
  }

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"create" | "edit" | "delete" | "">("")
  const [currentProgram, setCurrentProgram] = useState<Program | null>(null)
  const [itemsPerPage, setItemsPerPage] = useState(perPage)
  const [searchQuery, setSearchQuery] = useState(search)

  // Navigation state for program management
  const [currentView, setCurrentView] = useState<"programs" | "program-pages">("programs")
  const [selectedProgram, setSelectedProgram] = useState<Program | null>(null)

  const handleOpenModal = (type: "create" | "edit" | "delete", program: Program | null = null) => {
    setModalType(type)
    setCurrentProgram(type === "create" ? { id: 0, name: "", code: "", school_id: null } : program)
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setModalType("")
    setCurrentProgram(null)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (modalType === "create") {
      if (currentProgram) {
        router.post("/programs", currentProgram, {
          onSuccess: () => {
            alert("Program created successfully!")
            handleCloseModal()
          },
          onError: (errors) => {
            console.error("Error creating program:", errors)
          },
        })
      }
    } else if (modalType === "edit" && currentProgram) {
      router.put(`/programs/${currentProgram.id}`, currentProgram, {
        onSuccess: () => {
          alert("Program updated successfully!")
          handleCloseModal()
        },
        onError: (errors) => {
          console.error("Error updating program:", errors)
        },
      })
    } else if (modalType === "delete" && currentProgram) {
      router.delete(`/programs/${currentProgram.id}`, {
        onSuccess: () => {
          alert("Program deleted successfully!")
          handleCloseModal()
        },
        onError: (errors) => {
          console.error("Error deleting program:", errors)
        },
      })
    }
  }

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    router.get("/programs", { search: searchQuery, per_page: itemsPerPage }, { preserveState: true })
  }

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number.parseInt(e.target.value, 10)
    setItemsPerPage(newPerPage)
    router.get("/programs", { per_page: newPerPage, search: searchQuery }, { preserveState: true })
  }

  const handlePageChange = (url: string | null) => {
    if (url) {
      router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true })
    }
  }

  // Navigation functions for program management
  const handleProgramManage = (program: Program) => {
    setSelectedProgram(program)
    setCurrentView("program-pages")
  }

  const handleProgramPageClick = (pageId: string) => {
    // Navigate to the specific program page
    const routeMap: { [key: string]: string } = {
      classrooms: "/classrooms",
      "class-timetables": "/class-timetables",
      "exam-rooms": "/exam-rooms",
      "exam-time-slots": "/exam-time-slots",
      "exam-timetable": "/exam-timetable",
      "manage-enrollments": "/manage-enrollments",
    }

    const route = routeMap[pageId]
    if (route) {
      const school = schools.find((s) => s.id === selectedProgram?.school_id)
      router.get(`${route}?school=${school?.id}&program=${selectedProgram?.id}`)
    }
  }

  const handleBackToPrograms = () => {
    setCurrentView("programs")
    setSelectedProgram(null)
  }

  return (
    <AuthenticatedLayout>
      <Head title="Programs" />

      {/* Programs Table View */}
      {currentView === "programs" && (
        <div className="p-6 bg-white rounded-lg shadow-md">
          <h1 className="text-2xl font-semibold mb-4">Programs</h1>

          <div className="flex justify-between items-center mb-4">
            <button
              onClick={() => handleOpenModal("create")}
              className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
            >
              + Add Program
            </button>

            <form onSubmit={handleSearch} className="flex items-center space-x-2">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search programs..."
                className="border rounded p-2 w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <button type="submit" className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Search
              </button>
            </form>

            <div>
              <label htmlFor="perPage" className="mr-2 text-sm font-medium text-gray-700">
                Items per page:
              </label>
              <select id="perPage" value={itemsPerPage} onChange={handlePerPageChange} className="border rounded p-2">
                <option value={5}>5</option>
                <option value={10}>10</option>
                <option value={15}>15</option>
                <option value={20}>20</option>
              </select>
            </div>
          </div>

          <table className="min-w-full border-collapse border border-gray-200">
            <thead className="bg-gray-100">
              <tr>
                <th className="px-4 py-2 border">ID</th>
                <th className="px-4 py-2 border">Name</th>
                <th className="px-4 py-2 border">Code</th>
                <th className="px-4 py-2 border">School</th>
                <th className="px-4 py-2 border">Actions</th>
              </tr>
            </thead>
            <tbody>
              {programs.data.map((program) => (
                <tr key={program.id} className="hover:bg-gray-50">
                  <td className="px-4 py-2 border">{program.id}</td>
                  <td className="px-4 py-2 border">{program.name}</td>
                  <td className="px-4 py-2 border">{program.code}</td>
                  <td className="px-4 py-2 border">
                    {schools.find((school) => school.id === program.school_id)?.name || "N/A"}
                  </td>
                  <td className="px-4 py-2 border">
                    <div className="flex space-x-2">
                      <button
                        onClick={() => handleProgramManage(program)}
                        className="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600"
                      >
                        Manage
                      </button>
                      <button
                        onClick={() => handleOpenModal("edit", program)}
                        className="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600"
                      >
                        Edit
                      </button>
                      <button
                        onClick={() => handleOpenModal("delete", program)}
                        className="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600"
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          <div className="mt-4 flex justify-between items-center">
            <p className="text-sm text-gray-600">
              Showing {programs.data.length} of {programs.total} programs
            </p>
            <div className="flex space-x-2">
              {programs.links.map((link, index) => (
                <button
                  key={index}
                  onClick={() => handlePageChange(link.url)}
                  className={`px-3 py-1 rounded ${
                    link.active ? "bg-blue-500 text-white" : "bg-gray-200 text-gray-700 hover:bg-gray-300"
                  }`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Program Management Pages View */}
      {currentView === "program-pages" && selectedProgram && (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 py-8">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {/* Breadcrumb and Back Button */}
            <div className="mb-6 flex items-center justify-between">
              <div className="flex items-center space-x-2 text-sm">
                <button
                  onClick={handleBackToPrograms}
                  className="text-blue-600 hover:text-blue-800 font-medium transition-colors duration-200"
                >
                  Programs
                </button>
                <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
                <span className="text-gray-600 font-medium">{selectedProgram.name}</span>
              </div>

              <button
                onClick={handleBackToPrograms}
                className="group flex items-center space-x-2 bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-2 rounded-xl font-semibold transition-all duration-300 hover:from-gray-600 hover:to-gray-700 hover:shadow-lg hover:shadow-gray-500/30 hover:-translate-y-1 transform"
              >
                <svg
                  className="w-4 h-4 transition-transform duration-300 group-hover:-translate-x-1"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                </svg>
                <span>Back to Programs</span>
              </button>
            </div>

            {/* Page Header */}
            <div className="mb-8">
              <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-2">
                {selectedProgram.name} Management
              </h1>
              <p className="text-gray-600 text-lg">Manage {selectedProgram.name} resources and data</p>
            </div>

            {/* Management Cards */}
            <div className="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-white/20 overflow-hidden">
              <div className="p-6 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-indigo-100">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center space-x-4">
                    <div className="flex items-center space-x-3">
                      <div className="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                        <span className="text-white font-bold text-lg">{selectedProgram.code?.[0]}</span>
                      </div>
                      <div>
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-800 border border-indigo-200">
                          {selectedProgram.code}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div className="text-right">
                    <h3 className="text-lg font-bold text-gray-900">{selectedProgram.name}</h3>
                    <p className="text-sm text-gray-600">Management Resources</p>
                  </div>
                </div>
              </div>

              <div className="p-8">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  {programPages.map((page) => (
                    <div
                      key={page.id}
                      onClick={() => handleProgramPageClick(page.id)}
                      className="group bg-gradient-to-br from-white to-indigo-50 rounded-xl p-6 border border-indigo-100 hover:border-indigo-300 shadow-lg hover:shadow-xl transition-all duration-300 cursor-pointer hover:-translate-y-2"
                    >
                      <div className="flex items-center justify-between mb-4">
                        <div
                          className={`w-12 h-12 bg-gradient-to-br ${page.color} rounded-xl flex items-center justify-center shadow-lg`}
                        >
                          <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={page.icon} />
                          </svg>
                        </div>
                        <svg
                          className="w-6 h-6 text-indigo-500 group-hover:text-indigo-600 transition-colors duration-300"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                          />
                        </svg>
                      </div>

                      <h3 className="font-bold text-gray-900 text-lg mb-2 group-hover:text-indigo-700 transition-colors duration-300">
                        {page.name}
                      </h3>
                      <p className="text-sm text-gray-600 mb-4">
                        {page.description} {selectedProgram.code}
                      </p>

                      <div className="flex items-center justify-between">
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-800 border border-indigo-200">
                          {selectedProgram.code}
                        </span>
                        <span className="text-sm text-gray-500 font-medium">Access now</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
          <div className="bg-white p-6 rounded shadow-md" style={{ width: "auto", maxWidth: "90%", minWidth: "300px" }}>
            <h2 className="text-xl font-bold mb-4">
              {modalType === "create" && "Add Program"}
              {modalType === "edit" && "Edit Program"}
              {modalType === "delete" && "Delete Program"}
            </h2>

            {modalType !== "delete" ? (
              <form onSubmit={handleSubmit}>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700">Name</label>
                  <input
                    type="text"
                    value={currentProgram?.name || ""}
                    onChange={(e) => setCurrentProgram((prev) => ({ ...prev!, name: e.target.value }))}
                    className="w-full border rounded p-2"
                    required
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700">Code</label>
                  <input
                    type="text"
                    value={currentProgram?.code || ""}
                    onChange={(e) => setCurrentProgram((prev) => ({ ...prev!, code: e.target.value }))}
                    className="w-full border rounded p-2"
                    required
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700">School</label>
                  <select
                    value={currentProgram?.school_id || ""}
                    onChange={(e) =>
                      setCurrentProgram((prev) => ({
                        ...prev!,
                        school_id: Number.parseInt(e.target.value, 10),
                      }))
                    }
                    className="w-full border rounded p-2"
                    required
                  >
                    <option value="" disabled>
                      Select a school
                    </option>
                    {schools.map((school) => (
                      <option key={school.id} value={school.id}>
                        {school.name}
                      </option>
                    ))}
                  </select>
                </div>
                <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                  {modalType === "create" ? "Create" : "Update"}
                </button>
                <button
                  type="button"
                  onClick={handleCloseModal}
                  className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2"
                >
                  Cancel
                </button>
              </form>
            ) : (
              <div>
                <p>Are you sure you want to delete this program?</p>
                <div className="mt-4 flex justify-end">
                  <button onClick={handleSubmit} className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    Delete
                  </button>
                  <button
                    onClick={handleCloseModal}
                    className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  )
}

export default Programs
