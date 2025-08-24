"use client"

import type React from "react"
import { useState } from "react"
import { Head, router, useForm, route } from "@inertiajs/react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { Checkbox } from "@/components/ui/checkbox"
import { Badge } from "@/components/ui/badge"
import { ArrowLeft, Users } from "lucide-react"

interface Props {
  students: Array<{
    id: number
    code: string
    first_name: string
    last_name: string
    email: string
  }>
  semesters: Array<{
    id: number
    name: string
    is_active: boolean
  }>
  classes: Array<{
    id: number
    name: string
  }>
  groups: Array<{
    id: number
    name: string
    class?: {
      name: string
    }
  }>
  units: Array<{
    id: number
    name: string
    code: string
  }>
  schoolCode: string
  schoolName: string
}

export default function BulkEnrollment({ students, semesters, classes, groups, units, schoolCode, schoolName }: Props) {
  const [selectedStudents, setSelectedStudents] = useState<string[]>([])
  const [selectedUnits, setSelectedUnits] = useState<number[]>([])
  const [studentCodesText, setStudentCodesText] = useState("")

  const { data, setData, post, processing, errors } = useForm({
    student_codes: [] as string[],
    group_id: "",
    unit_ids: [] as number[],
    semester_id: "",
  })

  const handleStudentToggle = (studentCode: string) => {
    const newSelected = selectedStudents.includes(studentCode)
      ? selectedStudents.filter((code) => code !== studentCode)
      : [...selectedStudents, studentCode]

    setSelectedStudents(newSelected)
    setData("student_codes", newSelected)
  }

  const handleUnitToggle = (unitId: number) => {
    const newSelected = selectedUnits.includes(unitId)
      ? selectedUnits.filter((id) => id !== unitId)
      : [...selectedUnits, unitId]

    setSelectedUnits(newSelected)
    setData("unit_ids", newSelected)
  }

  const handleStudentCodesTextChange = (value: string) => {
    setStudentCodesText(value)
    const codes = value
      .split("\n")
      .map((line) => line.trim())
      .filter((line) => line.length > 0)
    setData("student_codes", codes)
    setSelectedStudents(codes)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    post(route(`faculty.enrollments.bulk.store.${schoolCode.toLowerCase()}`))
  }

  const handleBack = () => {
    router.visit(route(`faculty.enrollments.${schoolCode.toLowerCase()}`))
  }

  return (
    <div className="container mx-auto py-8 space-y-6">
      <Head title={`${schoolName} - Bulk Enrollment`} />

      {/* Header */}
      <div className="flex items-center gap-4">
        <Button variant="outline" onClick={handleBack}>
          <ArrowLeft className="h-4 w-4 mr-2" />
          Back to Enrollments
        </Button>
        <div>
          <h1 className="text-3xl font-bold">Bulk Enrollment</h1>
          <p className="text-muted-foreground">{schoolName}</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Student Selection */}
          <Card>
            <CardHeader>
              <CardTitle>Select Students</CardTitle>
              <CardDescription>Choose students to enroll or paste student codes</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <Label htmlFor="student-codes-text">Student Codes (one per line)</Label>
                <Textarea
                  id="student-codes-text"
                  placeholder="Enter student codes, one per line..."
                  value={studentCodesText}
                  onChange={(e) => handleStudentCodesTextChange(e.target.value)}
                  rows={6}
                />
                {errors.student_codes && <p className="text-sm text-red-600 mt-1">{errors.student_codes}</p>}
              </div>

              <div className="border-t pt-4">
                <Label>Or select from list:</Label>
                <div className="max-h-64 overflow-y-auto space-y-2 mt-2">
                  {students?.map((student) => (
                    <div key={student.id} className="flex items-center space-x-2">
                      <Checkbox
                        id={`student-${student.id}`}
                        checked={selectedStudents.includes(student.code)}
                        onCheckedChange={() => handleStudentToggle(student.code)}
                      />
                      <Label htmlFor={`student-${student.id}`} className="flex-1 cursor-pointer">
                        <div>
                          <div className="font-medium">{student.code}</div>
                          <div className="text-sm text-muted-foreground">
                            {student.first_name} {student.last_name}
                          </div>
                        </div>
                      </Label>
                    </div>
                  ))}
                </div>
              </div>

              {selectedStudents.length > 0 && (
                <div>
                  <Label>Selected Students ({selectedStudents.length}):</Label>
                  <div className="flex flex-wrap gap-1 mt-2">
                    {selectedStudents.slice(0, 10).map((code) => (
                      <Badge key={code} variant="secondary">
                        {code}
                      </Badge>
                    ))}
                    {selectedStudents.length > 10 && (
                      <Badge variant="outline">+{selectedStudents.length - 10} more</Badge>
                    )}
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Enrollment Details */}
          <Card>
            <CardHeader>
              <CardTitle>Enrollment Details</CardTitle>
              <CardDescription>Select semester, group, and units for enrollment</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <Label htmlFor="semester">Semester</Label>
                <Select value={data.semester_id} onValueChange={(value) => setData("semester_id", value)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select semester" />
                  </SelectTrigger>
                  <SelectContent>
                    {semesters?.map((semester) => (
                      <SelectItem key={semester.id} value={semester.id.toString()}>
                        {semester.name} {semester.is_active && "(Active)"}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.semester_id && <p className="text-sm text-red-600 mt-1">{errors.semester_id}</p>}
              </div>

              <div>
                <Label htmlFor="group">Group</Label>
                <Select value={data.group_id} onValueChange={(value) => setData("group_id", value)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select group" />
                  </SelectTrigger>
                  <SelectContent>
                    {groups?.map((group) => (
                      <SelectItem key={group.id} value={group.id.toString()}>
                        {group.name} {group.class?.name && `(${group.class.name})`}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.group_id && <p className="text-sm text-red-600 mt-1">{errors.group_id}</p>}
              </div>

              <div>
                <Label>Units to Enroll</Label>
                <div className="max-h-48 overflow-y-auto space-y-2 mt-2 border rounded-md p-3">
                  {units?.map((unit) => (
                    <div key={unit.id} className="flex items-center space-x-2">
                      <Checkbox
                        id={`unit-${unit.id}`}
                        checked={selectedUnits.includes(unit.id)}
                        onCheckedChange={() => handleUnitToggle(unit.id)}
                      />
                      <Label htmlFor={`unit-${unit.id}`} className="flex-1 cursor-pointer">
                        <div>
                          <div className="font-medium">{unit.name}</div>
                          {unit.code && <div className="text-sm text-muted-foreground">{unit.code}</div>}
                        </div>
                      </Label>
                    </div>
                  ))}
                </div>
                {errors.unit_ids && <p className="text-sm text-red-600 mt-1">{errors.unit_ids}</p>}
              </div>

              {selectedUnits.length > 0 && (
                <div>
                  <Label>Selected Units ({selectedUnits.length}):</Label>
                  <div className="flex flex-wrap gap-1 mt-2">
                    {selectedUnits.slice(0, 5).map((unitId) => {
                      const unit = units?.find((u) => u.id === unitId)
                      return unit ? (
                        <Badge key={unitId} variant="secondary">
                          {unit.name}
                        </Badge>
                      ) : null
                    })}
                    {selectedUnits.length > 5 && <Badge variant="outline">+{selectedUnits.length - 5} more</Badge>}
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Summary and Submit */}
        <Card>
          <CardHeader>
            <CardTitle>Enrollment Summary</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div className="text-center">
                <div className="text-2xl font-bold text-blue-600">{selectedStudents.length}</div>
                <div className="text-sm text-muted-foreground">Students Selected</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-green-600">{selectedUnits.length}</div>
                <div className="text-sm text-muted-foreground">Units Selected</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-purple-600">
                  {selectedStudents.length * selectedUnits.length}
                </div>
                <div className="text-sm text-muted-foreground">Total Enrollments</div>
              </div>
            </div>

            <div className="flex justify-end gap-4">
              <Button type="button" variant="outline" onClick={handleBack}>
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={processing || selectedStudents.length === 0 || selectedUnits.length === 0}
              >
                <Users className="h-4 w-4 mr-2" />
                {processing ? "Creating Enrollments..." : "Create Enrollments"}
              </Button>
            </div>
          </CardContent>
        </Card>
      </form>
    </div>
  )
}
