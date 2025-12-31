import React from "react";
import { ReactFlowProvider } from "@xyflow/react";
import FlowCanvas from "./flowCanvas/FlowCanvas";
import { Form, Formik } from "formik";

export default function Workflows({id}) {
  return (
    <ReactFlowProvider>
      <div style={{ display: "flex", height: "100vh" }}>
        <Formik
          initialValues={
          {
          }}

          onSubmit={(values) => {
            console.log("Final Values", values);
          }}>
          {(formik) => (
            <Form style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
              <div style={{ flex: 1 }}>
                <FlowCanvas formik={formik} id={id}  />
              </div>

            </Form>

          )}
        </Formik>

      </div>
    </ReactFlowProvider>
  );
}
