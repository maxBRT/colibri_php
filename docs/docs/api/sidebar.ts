import type { SidebarsConfig } from "@docusaurus/plugin-content-docs";

const sidebar: SidebarsConfig = {
  apisidebar: [
    {
      type: "category",
      label: "Categories",
      link: {
        type: "doc",
        id: "api/categories",
      },
      items: [
        {
          type: "doc",
          id: "api/list-categories",
          label: "List categories",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "Sources",
      link: {
        type: "doc",
        id: "api/sources",
      },
      items: [
        {
          type: "doc",
          id: "api/list-sources",
          label: "List sources",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "Posts",
      link: {
        type: "doc",
        id: "api/posts",
      },
      items: [
        {
          type: "doc",
          id: "api/list-posts",
          label: "List posts",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "Health",
      items: [
        {
          type: "doc",
          id: "api/health-check",
          label: "Health check",
          className: "api-method get",
        },
      ],
    },
  ],
};

export default sidebar.apisidebar;
