"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { AlertCircle, CheckCircle, XCircle, Clock, Zap, RefreshCw, Settings, Users } from "lucide-react"
import { toast } from "react-hot-toast"
import axios from "axios"

interface ConflictResolutionProps {
  classTimetables: any
  constraints: any
  detectedConflicts: any[]
  onConflictsResolved: () => void
}

export default function ConflictResolutionComponent({
  classTimetables,
  constraints,
  detectedConflicts,
  onConflictsResolved,
}: ConflictResolutionProps) {
  const [isResolving, setIsResolving] = useState(false)
  const [resolutionStrategy, setResolutionStrategy] = useState("auto")
  const [resolutionResult, setResolutionResult] = useState<any>(null)

  const resolveConflicts = async (strategy: string = resolutionStrategy) => {
    setIsResolving(true)
    setResolutionResult(null)

    try {
      const response = await axios.post("/api/resolve-conflicts", {
        strategy: strategy,
        // Add filters if needed
        semester_id: null,
        class_id: null,
        group_id: null,
      })

      if (response.data.success) {
        setResolutionResult(response.data)
        toast.success(`✅ Resolved ${response.data.conflicts_resolved} conflicts!`)

        // Refresh the parent component
        onConflictsResolved()
      } else {
        toast.error("Failed to resolve conflicts")
      }
    } catch (error: any) {
      console.error("Error resolving conflicts:", error)
      toast.error(error.response?.data?.message || "Failed to resolve conflicts")
    } finally {
      setIsResolving(false)
    }
  }

  const getStrategyDescription = (strategy: string) => {
    switch (strategy) {
      case "auto":
        return "Automatically applies the best resolution method for each conflict type"
      case "reschedule":
        return "Moves conflicting sessions to alternative time slots"
      case "split_groups":
        return "Creates sub-groups to reduce scheduling conflicts"
      default:
        return "Unknown strategy"
    }
  }

  const getConflictSeverityColor = (severity: string) => {
    switch (severity) {
      case "high":
        return "text-red-600 bg-red-50 border-red-200"
      case "medium":
        return "text-yellow-600 bg-yellow-50 border-yellow-200"
      case "low":
        return "text-blue-600 bg-blue-50 border-blue-200"
      default:
        return "text-gray-600 bg-gray-50 border-gray-200"
    }
  }

  return (
    <div className="space-y-6">
      {/* Conflict Resolution Controls */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center">
            <Zap className="w-5 h-5 mr-2 text-orange-500" />
            Automatic Conflict Resolution
          </CardTitle>
          <CardDescription>Choose a strategy to automatically resolve scheduling conflicts</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Strategy Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Resolution Strategy</label>
            <div className="space-y-3">
              {[
                { value: "auto", label: "Smart Auto-Resolution", icon: Zap },
                { value: "reschedule", label: "Reschedule Conflicts", icon: RefreshCw },
                { value: "split_groups", label: "Split Groups", icon: Users },
              ].map((strategy) => (
                <div key={strategy.value} className="flex items-start space-x-3">
                  <input
                    type="radio"
                    id={strategy.value}
                    name="strategy"
                    value={strategy.value}
                    checked={resolutionStrategy === strategy.value}
                    onChange={(e) => setResolutionStrategy(e.target.value)}
                    className="mt-1"
                  />
                  <div className="flex-1">
                    <label htmlFor={strategy.value} className="flex items-center cursor-pointer">
                      <strategy.icon className="w-4 h-4 mr-2 text-gray-500" />
                      <span className="font-medium text-gray-900">{strategy.label}</span>
                    </label>
                    <p className="text-sm text-gray-600 mt-1">{getStrategyDescription(strategy.value)}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Resolution Actions */}
          <div className="flex space-x-3 pt-4 border-t">
            <Button
              onClick={() => resolveConflicts()}
              disabled={isResolving || detectedConflicts.length === 0}
              className="bg-orange-500 hover:bg-orange-600 text-white"
            >
              {isResolving ? (
                <>
                  <Clock className="w-4 h-4 mr-2 animate-spin" />
                  Resolving Conflicts...
                </>
              ) : (
                <>
                  <Zap className="w-4 h-4 mr-2" />
                  Resolve {detectedConflicts.length} Conflicts
                </>
              )}
            </Button>

            <Button
              onClick={() => resolveConflicts("auto")}
              disabled={isResolving}
              variant="outline"
              className="border-orange-300 text-orange-700 hover:bg-orange-50"
            >
              <Settings className="w-4 h-4 mr-2" />
              Quick Auto-Fix
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Resolution Results */}
      {resolutionResult && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              {resolutionResult.conflicts_resolved > 0 ? (
                <CheckCircle className="w-5 h-5 mr-2 text-green-500" />
              ) : (
                <XCircle className="w-5 h-5 mr-2 text-red-500" />
              )}
              Resolution Results
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {/* Summary */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                  <div className="text-2xl font-bold text-green-600">{resolutionResult.conflicts_resolved}</div>
                  <div className="text-sm text-green-700">Conflicts Resolved</div>
                </div>

                <div className="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                  <div className="text-2xl font-bold text-blue-600">
                    {resolutionResult.resolution_details?.length || 0}
                  </div>
                  <div className="text-sm text-blue-700">Actions Taken</div>
                </div>

                <div className="text-center p-4 bg-gray-50 rounded-lg border border-gray-200">
                  <div className="text-2xl font-bold text-gray-600">
                    {Object.values(resolutionResult.remaining_conflicts || {}).reduce(
                      (sum: number, conflicts: any) => sum + (Array.isArray(conflicts) ? conflicts.length : 0),
                      0,
                    )}
                  </div>
                  <div className="text-sm text-gray-700">Remaining Conflicts</div>
                </div>
              </div>

              {/* Resolution Details */}
              {resolutionResult.resolution_details && resolutionResult.resolution_details.length > 0 && (
                <div>
                  <h4 className="font-medium text-gray-900 mb-3">Actions Taken:</h4>
                  <div className="space-y-2">
                    {resolutionResult.resolution_details.map((detail: string, index: number) => (
                      <div key={index} className="flex items-start space-x-2">
                        <CheckCircle className="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" />
                        <span className="text-sm text-gray-700">{detail}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Remaining Conflicts */}
              {resolutionResult.remaining_conflicts &&
                Object.values(resolutionResult.remaining_conflicts).some(
                  (conflicts: any) => Array.isArray(conflicts) && conflicts.length > 0,
                ) && (
                  <div>
                    <h4 className="font-medium text-gray-900 mb-3">Remaining Conflicts:</h4>
                    <div className="space-y-2">
                      {Object.entries(resolutionResult.remaining_conflicts).map(([type, conflicts]: [string, any]) => {
                        if (!Array.isArray(conflicts) || conflicts.length === 0) return null

                        return (
                          <Alert key={type} className="border-yellow-200 bg-yellow-50">
                            <AlertCircle className="h-4 w-4 text-yellow-600" />
                            <AlertDescription>
                              <div className="flex justify-between items-center">
                                <span className="text-yellow-700">
                                  <strong>{conflicts.length}</strong> {type.replace("_", " ")} still need manual
                                  resolution
                                </span>
                                <Badge variant="outline" className="border-yellow-300 text-yellow-700">
                                  Manual Review Required
                                </Badge>
                              </div>
                            </AlertDescription>
                          </Alert>
                        )
                      })}
                    </div>
                  </div>
                )}

              {/* Success Message */}
              {resolutionResult.conflicts_resolved > 0 && (
                <Alert className="border-green-200 bg-green-50">
                  <CheckCircle className="h-4 w-4 text-green-600" />
                  <AlertDescription className="text-green-700">
                    <strong>Success!</strong> {resolutionResult.message}
                  </AlertDescription>
                </Alert>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Current Conflicts Summary */}
      {detectedConflicts.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <AlertCircle className="w-5 h-5 mr-2 text-red-500" />
              Current Conflicts ({detectedConflicts.length})
            </CardTitle>
            <CardDescription>These conflicts will be addressed by the resolution process</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {detectedConflicts.slice(0, 5).map((conflict, index) => (
                <div key={index} className={`p-3 rounded-lg border ${getConflictSeverityColor(conflict.severity)}`}>
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center mb-1">
                        <Badge variant={conflict.severity === "high" ? "destructive" : "default"} className="mr-2">
                          {conflict.type.replace("_", " ").toUpperCase()}
                        </Badge>
                        <Badge variant="outline" className="text-xs">
                          {conflict.severity.toUpperCase()}
                        </Badge>
                      </div>
                      <p className="text-sm font-medium">{conflict.description}</p>
                    </div>
                  </div>
                </div>
              ))}

              {detectedConflicts.length > 5 && (
                <div className="text-center text-sm text-gray-500 pt-2 border-t">
                  ... and {detectedConflicts.length - 5} more conflicts
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
