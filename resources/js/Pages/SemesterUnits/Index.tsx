"use client"

// Update the SemesterUnits component
import type React from "react"
import { useState, useEffect } from "react"
import { Head, usePage, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

interface Semester {
  id: number
  name: string
  units: Unit[]
}

// Update the Unit interface to include the is_suggestion property
interface Unit {
  id: number
  name: string
  code: string
  pivot_class_id: number // Changed from pivot.class_id to match the controller
  is_suggestion?: boolean // Add this property to identify suggested units
}

interface Class {
  id: number
  name: string
}

const SemesterUnits = () => {
  const {
    semesters = [],
    units = [],
    classes = [],
  } = usePage().props as {
    semesters: Semester[]
    units: Unit[]
    classes: Class[]
  }

  const [selectedSemester, setSelectedSemester] = useState<number | "">("")
  const [selectedClass, setSelectedClass] = useState<number | "">("")
  const [selectedUnits, setSelectedUnits] = useState<number[]>([])
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [currentUnit, setCurrentUnit] = useState<{ semesterId: number; unitId: number; classId: number } | null>(null)

  // Debug: Log semesters and their units
  useEffect(() => {
    console.log("Semesters:", semesters)
    semesters.forEach((semester) => {
      console.log(`Semester ${semester.name} has ${semester.units ? semester.units.length : 0} units`)
      if (semester.units && semester.units.length > 0) {
        console.log("First unit details:", semester.units[0])
      }
    })
  }, [semesters])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (selectedSemester && selectedClass) {
      router.post(
        "/semester-units",
        {
          semester_id: selectedSemester,
          class_id: selectedClass,
          unit_ids: selectedUnits,
        },
        {
          onSuccess: () => {
            alert("Units assigned to class in semester successfully!")
            setSelectedSemester("")
            setSelectedClass("")
            setSelectedUnits([])
          },
          onError: (errors) => {
            console.error("Error assigning units:", errors)
          },
        },
      )
    }
  }

  const handleEditUnit = (semesterId: number, unitId: number, classId: number) => {
    setCurrentUnit({ semesterId, unitId, classId })
    setIsEditModalOpen(true)
  }

  const handleDeleteUnit = (semesterId: number, unitId: number) => {
    if (confirm("Are you sure you want to delete this unit?")) {
      router.delete(`/semester-units/${semesterId}/units/${unitId}`, {
        onSuccess: () => alert("Unit removed successfully!"),
      })
    }
  }

  const handleUpdateUnit = (e: React.FormEvent) => {
    e.preventDefault()
    if (currentUnit) {
      router.put(
        `/semester-units/${currentUnit.semesterId}/units/${currentUnit.unitId}`,
        {
          class_id: currentUnit.classId,
        },
        {
          onSuccess: () => {
            alert("Unit updated successfully!")
            setIsEditModalOpen(false)
            setCurrentUnit(null)
          },
        },
      )
    }
  }

  // Function to assign a suggested unit
  const handleAssignSuggestedUnit = (semesterId: number, unitId: number, classId: number) => {
    if (confirm("Do you want to assign this suggested unit?")) {
      router.post(
        "/semester-units",
        {
          semester_id: semesterId,
          class_id: classId,
          unit_ids: [unitId],
        },
        {
          onSuccess: () => alert("Unit assigned successfully!"),
        },
      )
    }
  }

  return (
    <AuthenticatedLayout>
      <Head title="Semester Units" />
      <div className="p-6 bg-gray-50 rounded-lg shadow-md">
        <h1 className="text-3xl font-bold text-blue-600 mb-6">Assign Units to Classes in Semesters</h1>
        <form onSubmit={handleSubmit} className="bg-white p-6 rounded-lg shadow-md">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700">Semester</label>
              <select
                value={selectedSemester || ""}
                onChange={(e) => setSelectedSemester(Number.parseInt(e.target.value, 10))}
                className="w-full border border-gray-300 rounded p-2 focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="" disabled>
                  Select a semester
                </option>
                {semesters.map((semester) => (
                  <option key={semester.id} value={semester.id}>
                    {semester.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Class</label>
              <select
                value={selectedClass || ""}
                onChange={(e) => setSelectedClass(Number.parseInt(e.target.value, 10))}
                className="w-full border border-gray-300 rounded p-2 focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="" disabled>
                  Select a class
                </option>
                {classes.map((classItem) => (
                  <option key={classItem.id} value={classItem.id}>
                    {classItem.name}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <div className="mt-6">
            <label className="block text-sm font-medium text-gray-700">Units</label>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
              {units.map((unit) => (
                <label key={unit.id} className="flex items-center space-x-2 bg-gray-100 p-2 rounded shadow-sm">
                  <input
                    type="checkbox"
                    value={unit.id}
                    checked={selectedUnits.includes(unit.id)}
                    onChange={(e) => {
                      const unitId = Number.parseInt(e.target.value, 10)
                      setSelectedUnits((prev) =>
                        e.target.checked ? [...prev, unitId] : prev.filter((id) => id !== unitId),
                      )
                    }}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  />
                  <span className="text-gray-800">
                    {unit.name} ({unit.code})
                  </span>
                </label>
              ))}
            </div>
          </div>
          <div className="mt-6 flex justify-end">
            <button
              type="submit"
              className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              Assign Units
            </button>
          </div>
        </form>
        <div className="mt-10">
          <h2 className="text-2xl font-semibold text-gray-800 mb-4">Assigned Units</h2>
          {semesters.map((semester) => (
            <div key={semester.id} className="mb-8">
              <h3 className="text-xl font-medium text-blue-500 mb-4">{semester.name}</h3>
              {semester.units && semester.units.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  {/* Group units by class */}
                  {classes.map((classItem) => {
                    // Filter units for this class
                    const classUnits = semester.units.filter((unit) => unit.pivot_class_id === classItem.id)

                    if (classUnits.length === 0) return null

                    return (
                      <div key={classItem.id} className="bg-white p-4 rounded-lg shadow-md border border-gray-200">
                        <h4 className="text-lg font-semibold text-gray-700 mb-2">{classItem.name}</h4>
                        <ul className="list-disc pl-6 text-gray-600">
                          {classUnits.map((unit) => (
                            <li
                              key={unit.id}
                              className={`flex justify-between items-center ${unit.is_suggestion ? "text-orange-500" : ""}`}
                            >
                              <span>
                                {unit.name} ({unit.code})
                                {unit.is_suggestion && (
                                  <span className="ml-2 text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">
                                    Suggested
                                  </span>
                                )}
                              </span>
                              <div className="flex space-x-2">
                                {!unit.is_suggestion && (
                                  <button
                                    onClick={() => handleDeleteUnit(semester.id, unit.id)}
                                    className="bg-red-500 text-white px-3 py-1 rounded border border-red-700 hover:bg-red-600 hover:border-red-800 focus:outline-none focus:ring-2 focus:ring-red-500"
                                  >
                                    Delete
                                  </button>
                                )}
                                {unit.is_suggestion && (
                                  <button
                                    onClick={() => handleAssignSuggestedUnit(semester.id, unit.id, classItem.id)}
                                    className="bg-green-500 text-white px-3 py-1 rounded border border-green-700 hover:bg-green-600 hover:border-green-800 focus:outline-none focus:ring-2 focus:ring-green-500"
                                  >
                                    Assign
                                  </button>
                                )}
                              </div>
                            </li>
                          ))}
                        </ul>
                      </div>
                    )
                  })}
                </div>
              ) : (
                <p className="text-gray-500 italic">No units assigned to this semester yet.</p>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Edit Modal */}
      {isEditModalOpen && currentUnit && (
        <div className="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
          <div className="bg-white p-6 rounded shadow-md" style={{ width: "auto", maxWidth: "90%", minWidth: "300px" }}>
            <h2 className="text-xl font-bold mb-4">Edit Unit</h2>
            <form onSubmit={handleUpdateUnit}>
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700">Class</label>
                <select
                  value={currentUnit.classId}
                  onChange={(e) =>
                    setCurrentUnit((prev) => ({
                      ...prev!,
                      classId: Number.parseInt(e.target.value, 10),
                    }))
                  }
                  className="w-full border rounded p-2"
                  required
                >
                  <option value="" disabled>
                    Select a class
                  </option>
                  {classes.map((classItem) => (
                    <option key={classItem.id} value={classItem.id}>
                      {classItem.name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="flex justify-end">
                <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                  Update
                </button>
                <button
                  type="button"
                  onClick={() => setIsEditModalOpen(false)}
                  className="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 ml-2"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      
    </AuthenticatedLayout>
  )
}

export default SemesterUnits
