import axios from 'axios';
import type { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';
import type { ApiResponse, ApiErrorResponse } from '../types/api.types';
import { getBackendBaseUrl } from '../utils/urlHelpers';

// API Base Configuration - Use env var or fallback to localhost
const API_BASE_URL = getBackendBaseUrl();

type CacheMatcher = string | RegExp | ((key: string) => boolean);

const responseCache = new Map<string, ApiResponse<any>>();

const cloneData = <T>(value: T): T => {
  if (value === null || typeof value !== 'object') return value;
  if (typeof structuredClone === 'function') {
    return structuredClone(value);
  }
  try {
    return JSON.parse(JSON.stringify(value)) as T;
  } catch (error) {
    console.warn('Cache clone fallback failed, returning original reference', error);
    return value;
  }
};

const sortObject = (input: unknown): unknown => {
  if (Array.isArray(input)) {
    return input.map(sortObject);
  }

  if (input && typeof input === 'object') {
    return Object.keys(input as Record<string, unknown>)
      .sort()
      .reduce<Record<string, unknown>>((acc, key) => {
        acc[key] = sortObject((input as Record<string, unknown>)[key]);
        return acc;
      }, {});
  }

  return input;
};

const buildCacheKey = (method: string, url: string, config?: AxiosRequestConfig): string => {
  if (config?.cacheKey) {
    return `${method.toUpperCase()}::${config.cacheKey}`;
  }

  const normalizedUrl = normalizeRelativeUrl(url) ?? url;
  const paramsPart = config?.params ? JSON.stringify(sortObject(config.params)) : '';
  const dataPart = config?.data ? JSON.stringify(sortObject(config.data)) : '';

  return `${method.toUpperCase()}::${normalizedUrl}?params=${paramsPart}&data=${dataPart}`;
};

const invalidateCacheInternal = (matcher?: CacheMatcher) => {
  if (!matcher) {
    responseCache.clear();
    return;
  }

  const predicate:
    | ((key: string) => boolean)
    = typeof matcher === 'function'
      ? matcher
      : typeof matcher === 'string'
        ? (key: string) => key.includes(matcher)
        : (key: string) => matcher.test(key);

  for (const key of Array.from(responseCache.keys())) {
    if (predicate(key)) {
      responseCache.delete(key);
    }
  }
};

const isAbsoluteUrl = (url: string): boolean => /^(?:[a-z][a-z0-9+.-]*:)?\/\//i.test(url);

const normalizeRelativeUrl = (url?: string): string | undefined => {
  if (!url) return url;
  if (isAbsoluteUrl(url)) return url;
  return url.replace(/^\/+/, '');
};

// Custom params serializer: arrays as repeated keys (e.g., a=1&a=2), no [] suffix
const serializeParams = (params?: Record<string, unknown>): string => {
  const usp = new URLSearchParams();
  if (!params) return usp.toString();
  const append = (key: string, value: unknown) => {
    if (value === undefined || value === null) return;
    usp.append(key, String(value));
  };
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null) return;
    if (Array.isArray(value)) {
      value.forEach(v => append(key, v));
    } else if (typeof value === 'object') {
      // Flatten simple objects into key=value JSON string to avoid axios default bracket format
      append(key, JSON.stringify(value));
    } else {
      append(key, value);
    }
  });
  return usp.toString();
};

// Create Axios instance
const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
  paramsSerializer: {
    serialize: serializeParams,
  },
});

// Request interceptor for adding auth token
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('access_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    if (config.baseURL) {
      const normalized = normalizeRelativeUrl(config.url);
      if (normalized !== undefined) {
        config.url = normalized;
      }
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Thông báo mặc định theo từng mã lỗi HTTP — dùng khi backend không trả message cụ thể
const STATUS_MESSAGES: Record<number, string> = {
  400: 'Yêu cầu không hợp lệ. Vui lòng kiểm tra lại thông tin đã nhập.',
  401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
  403: 'Bạn không có quyền thực hiện thao tác này.',
  404: 'Không tìm thấy dữ liệu yêu cầu.',
  408: 'Yêu cầu mất quá nhiều thời gian. Vui lòng thử lại.',
  409: 'Dữ liệu bị xung đột. Vui lòng tải lại trang và thử lại.',
  413: 'Tệp tải lên quá lớn. Vui lòng chọn tệp nhỏ hơn.',
  422: 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại các trường đã nhập.',
  429: 'Bạn thao tác quá nhanh. Vui lòng chờ giây lát rồi thử lại.',
  500: 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau ít phút.',
  502: 'Máy chủ tạm thời không phản hồi. Vui lòng thử lại sau.',
  503: 'Hệ thống đang bảo trì hoặc quá tải. Vui lòng thử lại sau.',
  504: 'Máy chủ phản hồi quá lâu. Vui lòng thử lại sau.',
};

// Gộp lỗi 422 dạng mảng (FastAPI mặc định) thành một câu dễ đọc
const summarizeValidationDetail = (detail: unknown): string | undefined => {
  if (!Array.isArray(detail)) return undefined;
  const parts = detail
    .map((d: any) => {
      const loc = Array.isArray(d?.loc)
        ? d.loc.filter((p: unknown) => p !== 'body' && p !== 'query' && p !== 'path').join('.')
        : '';
      const msg = d?.msg || 'Giá trị không hợp lệ';
      return loc ? `${loc}: ${msg}` : msg;
    })
    .filter(Boolean);
  if (parts.length === 0) return undefined;
  const head = parts.slice(0, 3).join('; ');
  return parts.length > 3 ? `${head} (và ${parts.length - 3} lỗi khác)` : head;
};

// Response interceptor for handling common responses
apiClient.interceptors.response.use(
  (response: AxiosResponse<ApiResponse<any>>) => {
    // Return the data directly for successful responses
    return response;
  },
  async (error) => {
    // const originalRequest = error.config; // not used when auto-refresh disabled

    // Ignore request cancellation so UI can silently stop pending calls
    if (axios.isCancel && axios.isCancel(error)) {
      return Promise.reject({ name: 'CanceledError', message: 'canceled' });
    }
    if (error?.code === 'ERR_CANCELED') {
      return Promise.reject({ name: 'CanceledError', message: 'canceled' });
    }

    // Không nhận được phản hồi từ máy chủ -> lỗi mạng hoặc hết thời gian chờ
    if (!error.response) {
      const isTimeout = error?.code === 'ECONNABORTED' || /timeout/i.test(error?.message || '');
      const networkMessage = isTimeout
        ? 'Máy chủ phản hồi quá lâu. Vui lòng kiểm tra kết nối mạng và thử lại.'
        : 'Không thể kết nối tới máy chủ. Vui lòng kiểm tra kết nối mạng và thử lại.';
      return Promise.reject({
        status_code: 0,
        message: networkMessage,
        errors: undefined,
      } as ApiErrorResponse);
    }

    const status = error.response.status;

    // Handle 401 Unauthorized - Token expired
    // NOTE: Automatic refresh is intentionally disabled to avoid refresh loops.
    if (status === 401) {
      // Clear local auth state and redirect to login page.
      try { localStorage.removeItem('access_token'); } catch (_) { }
      try { localStorage.removeItem('refresh_token'); } catch (_) { }
      try { localStorage.removeItem('user_info'); } catch (_) { }
      try { localStorage.removeItem('token_expires_at'); } catch (_) { }
      try { localStorage.removeItem('token_expires_in'); } catch (_) { }
      window.location.href = '/login';
    }

    // Handle other errors
    // Backend trả lỗi theo envelope {status_code, message, data}; một số trường hợp (lỗi 422
    // mặc định của FastAPI) lại dùng `detail`. Đọc cả hai để luôn lấy được thông báo cụ thể.
    const serverData = error.response?.data || {};
    const serverErrors = serverData?.data || serverData?.errors || undefined;
    const validationSummary = summarizeValidationDetail(serverData?.detail);

    const message =
      serverData?.message ||
      validationSummary ||
      (typeof serverData?.detail === 'string' ? serverData.detail : undefined) ||
      STATUS_MESSAGES[status] ||
      'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.';

    const errorResponse: ApiErrorResponse = {
      status_code: status || 500,
      message,
      errors: serverErrors,
    };

    return Promise.reject(errorResponse);
  }
);

// Generic API methods
export const api = {
  // GET request with in-memory cache
  get: <T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    const requestConfig = config ? { ...config } : undefined;
    const cacheKey = buildCacheKey('GET', url, requestConfig);
    const skipReadFromCache = requestConfig?.forceRefresh || requestConfig?.skipCache;
    const skipWriteToCache = requestConfig?.skipCache;

    if (!skipReadFromCache && responseCache.has(cacheKey)) {
      return Promise.resolve(cloneData(responseCache.get(cacheKey) as ApiResponse<T>));
    }

    if (requestConfig?.signal?.aborted) {
      return Promise.reject({ name: 'CanceledError', message: 'canceled' });
    }

    return apiClient.get<ApiResponse<T>>(url, requestConfig).then((response) => {
      const safeData = cloneData(response.data);
      if (!skipWriteToCache) {
        responseCache.set(cacheKey, safeData);
      }
      return cloneData(safeData);
    });
  },

  // POST request - clear cache after mutation
  post: <T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.post<ApiResponse<T>>(url, data, config).then((response) => {
      if (!config?.preserveCache) {
        invalidateCacheInternal();
      }
      return response.data;
    }),

  // PUT request - clear cache after mutation
  put: <T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.put<ApiResponse<T>>(url, data, config).then((response) => {
      if (!config?.preserveCache) {
        invalidateCacheInternal();
      }
      return response.data;
    }),

  // PATCH request - clear cache after mutation
  patch: <T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.patch<ApiResponse<T>>(url, data, config).then((response) => {
      if (!config?.preserveCache) {
        invalidateCacheInternal();
      }
      return response.data;
    }),

  // DELETE request - clear cache after mutation
  delete: <T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.delete<ApiResponse<T>>(url, config).then((response) => {
      if (!config?.preserveCache) {
        invalidateCacheInternal();
      }
      return response.data;
    }),

  // File upload - treat as mutation and clear cache
  upload: <T>(url: string, formData: FormData, config?: AxiosRequestConfig): Promise<ApiResponse<T>> =>
    apiClient.post<ApiResponse<T>>(url, formData, {
      ...config,
      headers: {
        'Content-Type': 'multipart/form-data',
        ...config?.headers,
      },
    }).then((response) => {
      if (!config?.preserveCache) {
        invalidateCacheInternal();
      }
      return response.data;
    }),

  // Cache utilities
  invalidateCache: (matcher?: CacheMatcher) => invalidateCacheInternal(matcher),
  getCacheKeys: (): string[] => Array.from(responseCache.keys()),
  clearCache: () => invalidateCacheInternal(),
};

// Export the configured axios instance for special cases
export { apiClient };
export default api;
