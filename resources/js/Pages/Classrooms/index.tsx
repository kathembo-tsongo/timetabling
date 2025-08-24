"use client"

import type React from "react"
import { useState } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { toast } from "react-hot-toast"

interface Classroom {
  id: number
  name: string
  capacity: number
  location: string
}

interface PaginationLinks {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedClassrooms {
  data: Classroom[]
  links: PaginationLinks[]
  total: number
  per_page: number
  current_page: number
}

const Classrooms = () => {
  const { classrooms, perPage, search } = usePage().props as {
    classrooms: PaginatedClassrooms
    perPage: number
    search: string
  }

  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalType, setModalType] = useState<"create" | "edit" | "delete" | "">("")
  const [currentClassroom, setCurrentClassroom] = useState<Classroom | null>(null)
  const [itemsPerPage, setItemsPerPage] = useState(perPage)
  const [searchQuery, setSearchQuery] = useState(search)

  const handleOpenModal = (type: "create" | "edit" | "delete", classroom: Classroom | null = null) => {
    setModalType(type)
    setCurrentClassroom(type === "create" ? { id: 0, name: "", capacity: 0, location: "" } : classroom)
    setIsModalOpen(true)
  }

  const handleCloseModal = () => {
    setIsModalOpen(false)
    setModalType("")
    setCurrentClassroom(null)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (modalType === "create") {
      router.post("/classrooms", currentClassroom, {
        onSuccess: () => {
          toast.success("Classroom created successfully!")
          handleCloseModal()
        },
        onError: (errors) => {
          console.error("Error creating classroom:", errors)
          toast.error("Failed to create classroom.")
        },
      })
    } else if (modalType === "edit" && currentClassroom) {
      router.put(`/classrooms/${currentClassroom.id}`, currentClassroom, {
        onSuccess: () => {
          toast.success("Classroom updated successfully!")
          handleCloseModal()
        },
        onError: (errors) => {
          console.error("Error updating classroom:", errors)
          toast.error("Failed to update classroom.")
        },
      })
    } else if (modalType === "delete" && currentClassroom) {
      // Use Inertia's delete method instead of axios
      router.delete(`/classrooms/${currentClassroom.id}`, {
        onSuccess: () => {
          toast.success("Classroom deleted successfully!")
          handleCloseModal()
        },
        onError: (errors) => {
          console.error("Error deleting classroom:", errors)
          toast.error("Failed to delete classroom. Please try again.")
        },
      })
    }
  }

  const handleDelete = (classroom: Classroom) => {
    if (confirm("Are you sure you want to delete this classroom?")) {
      // Use Inertia's delete method for consistency
      router.delete(`/classrooms/${classroom.id}`, {
        onSuccess: () => {
          toast.success("Classroom deleted successfully!")
        },
        onError: (errors) => {
          console.error("Error deleting classroom:", errors)
          toast.error("Failed to delete classroom. Please try again.")
        },
      })
    }
  }

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    router.get("/classrooms", { search: searchQuery, per_page: itemsPerPage }, { preserveState: true })
  }

  const handlePerPageChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newPerPage = Number.parseInt(e.target.value, 10)
    setItemsPerPage(newPerPage)
    router.get("/classrooms", { per_page: newPerPage, search: searchQuery }, { preserveState: true })
  }

  const handlePageChange = (url: string | null) => {
    if (url) {
      router.get(url, { per_page: itemsPerPage, search: searchQuery }, { preserveState: true })
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Classrooms" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        <h1 className="text-2xl font-semibold mb-4">Classrooms</h1>
        <div className="flex justify-between items-center mb-4">
          <button
            onClick={() => handleOpenModal("create")}
            className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
          >
            + Add Classroom
          </button>
          <form onSubmit={handleSearch} className="flex items-center space-x-2">
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search classrooms..."
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
              <th className="px-4 py-2 border">Name</th>
              <th className="px-4 py-2 border">Capacity</th>
              <th className="px-4 py-2 border">Location</th>
              <th className="px-4 py-2 border">Actions</th>
            </tr>
          </thead>
          <tbody>
            {classrooms.data.map((classroom) => (
              <tr key={classroom.id} className="border-b hover:bg-gray-50">
                <td className="px-4 py-2 border">{classroom.name}</td>
                <td className="px-4 py-2 border">{classroom.capacity}</td>
                <td className="px-4 py-2 border">{classroom.location}</td>
                <td className="px-4 py-2 border text-center">
                  <button
                    onClick={() => handleOpenModal("edit", classroom)}
                    className="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 mr-2"
                  >
                    Edit
                  </button>
                  <button
                    onClick={() => handleDelete(classroom)}
                    className="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="mt-4 flex justify-between items-center">
          <p className="text-sm text-gray-600">
            Showing {classrooms.data.length} of {classrooms.total} classrooms
          </p>
          <div className="flex space-x-2">
            {classrooms.links.map((link, index) => (
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

      {/* Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
          <div className="bg-white p-6 rounded shadow-md" style={{ width: "auto", maxWidth: "90%", minWidth: "300px" }}>
            <h2 className="text-xl font-bold mb-4">
              {modalType === "create" && "Add Classroom"}
              {modalType === "edit" && "Edit Classroom"}
              {modalType === "delete" && "Delete Classroom"}
            </h2>
            {modalType !== "delete" ? (
              <form onSubmit={handleSubmit}>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700">Name</label>
                  <input
                    type="text"
                    value={currentClassroom?.name || ""}
                    onChange={(e) => setCurrentClassroom((prev) => ({ ...prev!, name: e.target.value }))}
                    className="w-full border rounded p-2"
                    required
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700">Capacity</label>
                  <input
                    type="number"
                    value={currentClassroom?.capacity || 0}
                    onChange={(e) => setCurrentClassroom((prev) => ({ ...prev!, capacity: +e.target.value }))}
                    className="w-full border rounded p-2"
                    required
                  />
                </div>
                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700">Location</label>
                  <input
                    type="text"
                    value={currentClassroom?.location || ""}
                    onChange={(e) => setCurrentClassroom((prev) => ({ ...prev!, location: e.target.value }))}
                    className="w-full border rounded p-2"
                    required
                  />
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
                <p>Are you sure you want to delete this classroom?</p>
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

export default Classrooms
