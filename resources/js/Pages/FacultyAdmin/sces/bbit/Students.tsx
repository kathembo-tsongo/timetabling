"use client"

import { useState } from "react"
import { Head, router } from "@inertiajs/react"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import { Badge } from "@/Components/ui/badge"
import { Input } from "@/Components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table"
import { Users, Plus, Edit, Trash2, Search } from "lucide-react"

interface Student {
  id: number
  code: string
  first_name: string
  last_name: string
  email: string
  created_at: string
}

interface Props {
  students: {
    data: Student[]
    links: any[]
    total: number
    current_page: number
    per_page: number
  }
  schoolCode: string
  schoolName: string
  errors?: {
    error?: string
  }
}

export default function SCESStudents({
  students,
  schoolCode = "SCES",
  schoolName = "School of Computing and Engineering Sciences",
  errors,
}: Props) {
  const [searchTerm, setSearchTerm] = useState("")

  const filteredStudents =
    students?.data?.filter((student) => {
      const matchesSearch =
        !searchTerm ||
        student.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        student.first_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        student.last_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        student.email.toLowerCase().includes(searchTerm.toLowerCase())

      return matchesSearch
    }) || []

  const handleCreateStudent = () => {
    router.visit(`/sces/students/create`)
  }

  const handleEditStudent = (studentId: number) => {
    router.visit(`/sces/students/${studentId}/edit`)
  }

  const handleDeleteStudent = (studentId: number) => {
    if (confirm("Are you sure you want to delete this student?")) {
      router.delete(`/sces/students/${studentId}`)
    }
  }

  if (errors?.error) {
    return (
      <AuthenticatedLayout
        header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">{schoolName} - Students</h2>}
      >
        <Head title={`${schoolName} - Students`} />
        <div className="py-12">
          <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <Card>
              <CardContent className="pt-6">
                <div className="text-center text-red-600">
                  <p>Error loading students: {errors.error}</p>
                  <Button onClick={() => window.location.reload()} className="mt-4">
                    Retry
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </AuthenticatedLayout>
    )
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <div>
            <h2 className="font-semibold text-xl text-gray-800 leading-tight">{schoolName}</h2>
            <p className="text-sm text-gray-600">Manage students in {schoolCode}</p>
          </div>
          <Badge variant="secondary">{schoolCode}</Badge>
        </div>
      }
    >
      <Head title={`${schoolName} - Students`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          {/* Statistics Card */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Students</CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{students?.total || 0}</div>
            </CardContent>
          </Card>

          {/* Actions and Search */}
          <Card>
            <CardContent className="pt-6">
              <div className="flex justify-between items-center gap-4">
                <div className="relative flex-1 max-w-sm">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <Input
                    placeholder="Search students..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-10"
                  />
                </div>
                <Button onClick={handleCreateStudent}>
                  <Plus className="h-4 w-4 mr-2" />
                  Add Student
                </Button>
              </div>
            </CardContent>
          </Card>

          {/* Students Table */}
          <Card>
            <CardHeader>
              <CardTitle>Students</CardTitle>
              <CardDescription>
                Showing {filteredStudents.length} of {students?.total || 0} students
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Student Code</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Registration Date</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredStudents.map((student) => (
                    <TableRow key={student.id}>
                      <TableCell className="font-medium">{student.code}</TableCell>
                      <TableCell>
                        {student.first_name} {student.last_name}
                      </TableCell>
                      <TableCell>{student.email}</TableCell>
                      <TableCell>{new Date(student.created_at).toLocaleDateString()}</TableCell>
                      <TableCell>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" onClick={() => handleEditStudent(student.id)}>
                            <Edit className="h-4 w-4" />
                          </Button>
                          <Button variant="destructive" size="sm" onClick={() => handleDeleteStudent(student.id)}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {filteredStudents.length === 0 && (
                <div className="text-center py-8 text-gray-500">No students found matching your criteria.</div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
