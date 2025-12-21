import React from "react";
import { ReactFlowProvider } from "@xyflow/react";
import Sidebar from "./Sidebar";
import FlowCanvas from "./FlowCanvas";
import { Form, Formik } from "formik";

export default function Workflows() {
  return (
    <ReactFlowProvider>
      <div style={{ display: "flex", height: "100vh" }}>
        <Sidebar />
        <Formik
          initialValues={{
            name: "",
            email: "",
            address: {
              city: "",
              country: "",
            },
          }}

          onSubmit={(values) => {
            console.log("Final Values", values);
          }}>
          {(formik) => (
            <Form style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
              <div style={{ flex: 1 }}>
                <FlowCanvas formik={formik} />
              </div>

            </Form>

          )}
        </Formik>

      </div>
    </ReactFlowProvider>
  );
}
