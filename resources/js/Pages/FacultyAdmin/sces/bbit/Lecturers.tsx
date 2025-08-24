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

interface Lecturer {
  id: number
  code: string
  first_name: string
  last_name: string
  email: string
  created_at: string
}

interface Props {
  lecturers: {
    data: Lecturer[]
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

export default function SCESLecturers({
  lecturers,
  schoolCode = "SCES",
  schoolName = "School of Computing and Engineering Sciences",
  errors,
}: Props) {
  const [searchTerm, setSearchTerm] = useState("")

  const filteredLecturers =
    lecturers?.data?.filter((lecturer) => {
      const matchesSearch =
        !searchTerm ||
        lecturer.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        lecturer.first_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        lecturer.last_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        lecturer.email.toLowerCase().includes(searchTerm.toLowerCase())

      return matchesSearch
    }) || []

  const handleCreateLecturer = () => {
    router.visit(`/sces/lecturers/create`)
  }

  const handleEditLecturer = (lecturerId: number) => {
    router.visit(`/sces/lecturers/${lecturerId}/edit`)
  }

  const handleDeleteLecturer = (lecturerId: number) => {
    if (confirm("Are you sure you want to delete this lecturer?")) {
      router.delete(`/sces/lecturers/${lecturerId}`)
    }
  }

  if (errors?.error) {
    return (
      <AuthenticatedLayout
        header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">{schoolName} - Lecturers</h2>}
      >
        <Head title={`${schoolName} - Lecturers`} />
        <div className="py-12">
          <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <Card>
              <CardContent className="pt-6">
                <div className="text-center text-red-600">
                  <p>Error loading lecturers: {errors.error}</p>
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
            <p className="text-sm text-gray-600">Manage lecturers in {schoolCode}</p>
          </div>
          <Badge variant="secondary">{schoolCode}</Badge>
        </div>
      }
    >
      <Head title={`${schoolName} - Lecturers`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          {/* Statistics Card */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Lecturers</CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{lecturers?.total || 0}</div>
            </CardContent>
          </Card>

          {/* Actions and Search */}
          <Card>
            <CardContent className="pt-6">
              <div className="flex justify-between items-center gap-4">
                <div className="relative flex-1 max-w-sm">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <Input
                    placeholder="Search lecturers..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-10"
                  />
                </div>
                <Button onClick={handleCreateLecturer}>
                  <Plus className="h-4 w-4 mr-2" />
                  Add Lecturer
                </Button>
              </div>
            </CardContent>
          </Card>

          {/* Lecturers Table */}
          <Card>
            <CardHeader>
              <CardTitle>Lecturers</CardTitle>
              <CardDescription>
                Showing {filteredLecturers.length} of {lecturers?.total || 0} lecturers
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Lecturer Code</TableHead>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Registration Date</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredLecturers.map((lecturer) => (
                    <TableRow key={lecturer.id}>
                      <TableCell className="font-medium">{lecturer.code}</TableCell>
                      <TableCell>
                        {lecturer.first_name} {lecturer.last_name}
                      </TableCell>
                      <TableCell>{lecturer.email}</TableCell>
                      <TableCell>{new Date(lecturer.created_at).toLocaleDateString()}</TableCell>
                      <TableCell>
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" onClick={() => handleEditLecturer(lecturer.id)}>
                            <Edit className="h-4 w-4" />
                          </Button>
                          <Button variant="destructive" size="sm" onClick={() => handleDeleteLecturer(lecturer.id)}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {filteredLecturers.length === 0 && (
                <div className="text-center py-8 text-gray-500">No lecturers found matching your criteria.</div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
